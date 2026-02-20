<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;

/**
 * Minimal audit report viewer (admin-only via /admin Basic Auth middleware).
 *
 * Reports are written to LOGS/ as markdown by CLI commands:
 * - regulation_audit_YYYYmmdd_HHMMSS.md
 * - ai_audit_YYYYmmdd_HHMMSS.md
 */
final class AuditController extends AppController
{
    public function index(): void
    {
        $type = strtolower(trim((string)($this->request->getQuery('type') ?? 'regulation')));
        if (!in_array($type, ['regulation','ai','all'], true)) { $type = 'regulation'; }

        $reports = $this->listReports($type);
        $latest = $reports[0] ?? null;
        $this->set(compact('type','reports','latest'));
    }

    public function latest(): \Cake\Http\Response|null
    {
        $type = strtolower(trim((string)($this->request->getQuery('type') ?? 'regulation')));
        if (!in_array($type, ['regulation','ai'], true)) { $type = 'regulation'; }
        $reports = $this->listReports($type);
        if (empty($reports)) {
            $this->Flash->error('Ingen rapporter fundet endnu. Kør CLI audit først.');
            return $this->redirect(['action' => 'index', '?' => ['type' => $type]]);
        }
        $file = (string)($reports[0]['file'] ?? '');
        if ($file === '') {
            return $this->redirect(['action' => 'index', '?' => ['type' => $type]]);
        }
        return $this->redirect(['action' => 'view', '?' => ['file' => $file]]);
    }

    public function view(): void
    {
        $file = (string)($this->request->getQuery('file') ?? '');
        $file = basename($file); // prevent path traversal
        $path = rtrim(LOGS, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
        if ($file === '' || !is_file($path)) {
            $this->set(['error' => 'Report not found', 'file' => $file, 'path' => $path, 'contentHtml' => null, 'contentMd' => null]);
            return;
        }

        $md = (string)file_get_contents($path);
        $html = $this->renderMarkdownLite($md);
        $meta = [
            'file' => $file,
            'path' => $path,
            'mtime' => filemtime($path) ?: null,
            'size' => filesize($path) ?: null,
        ];
        $this->set(['error' => null, 'meta' => $meta, 'contentHtml' => $html, 'contentMd' => $md]);
    }

    /**
     * @return array<int,array{file:string,mtime:int,size:int,type:string}>
     */
    private function listReports(string $type): array
    {
        $patterns = [];
        if ($type === 'regulation') { $patterns[] = 'regulation_audit_*.md'; }
        elseif ($type === 'ai') { $patterns[] = 'ai_audit_*.md'; }
        else { $patterns = ['regulation_audit_*.md', 'ai_audit_*.md']; }

        $out = [];
        foreach ($patterns as $pat) {
            foreach (glob(rtrim(LOGS, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $pat) ?: [] as $p) {
                $file = basename($p);
                $out[] = [
                    'file' => $file,
                    'mtime' => filemtime($p) ?: 0,
                    'size' => filesize($p) ?: 0,
                    'type' => str_starts_with($file, 'ai_audit_') ? 'ai' : 'regulation',
                ];
            }
        }
        usort($out, static fn($a, $b) => ($b['mtime'] <=> $a['mtime']));
        return $out;
    }

    /**
     * Minimal markdown renderer for our audit output.
     * Supports: headings, unordered lists, code fences, paragraphs.
     */
    private function renderMarkdownLite(string $md): string
    {
        $md = str_replace(["\r\n", "\r"], "\n", $md);
        $lines = explode("\n", $md);
        $html = [];

        $inCode = false;
        $codeLang = '';
        $listOpen = false;

        $flushList = function() use (&$html, &$listOpen): void {
            if ($listOpen) { $html[] = '</ul>'; $listOpen = false; }
        };

        $esc = static function(string $s): string {
            return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        foreach ($lines as $ln) {
            $raw = $ln;
            $ln = rtrim($ln, "\n");

            if (preg_match('/^```(\w+)?\s*$/', $ln, $m)) {
                if ($inCode) {
                    $html[] = '</code></pre>';
                    $inCode = false;
                    $codeLang = '';
                } else {
                    $flushList();
                    $inCode = true;
                    $codeLang = (string)($m[1] ?? '');
                    $cls = $codeLang !== '' ? ' class="lang-' . $esc($codeLang) . '"' : '';
                    $html[] = '<pre><code' . $cls . '>';
                }
                continue;
            }

            if ($inCode) {
                $html[] = $esc($raw) . "\n";
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.*)$/', $ln, $m)) {
                $flushList();
                $lvl = strlen($m[1]);
                $txt = trim((string)$m[2]);
                $id = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $txt) ?? '');
                $id = trim($id, '-');
                if ($id === '') { $id = 'h' . $lvl . '_' . substr(sha1($txt), 0, 8); }
                $html[] = '<h' . $lvl . ' id="' . $esc($id) . '">' . $esc($txt) . '</h' . $lvl . '>';
                continue;
            }

            if (preg_match('/^\s*-\s+(.*)$/', $ln, $m)) {
                if (!$listOpen) { $flushList(); $html[] = '<ul>'; $listOpen = true; }
                $html[] = '<li>' . $esc(trim((string)$m[1])) . '</li>';
                continue;
            }

            if (trim($ln) === '') {
                $flushList();
                continue;
            }

            $flushList();
            $html[] = '<p>' . $esc($ln) . '</p>';
        }

        if ($inCode) { $html[] = '</code></pre>'; }
        if ($listOpen) { $html[] = '</ul>'; }

        return implode("\n", $html);
    }
}
