<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\FixtureRepository;
use App\Service\RegulationIndex;
use App\Service\ScenarioRunner;
use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use GuzzleHttp\Client;

/**
 * Generate a Groq-backed audit report comparing our flow/API behavior with Regulation (EU) 2021/782 (DA).
 *
 * Usage (PowerShell):
 *   php bin/cake.php regulation_audit
 *
 * Requires:
 *   - GROQ_API_KEY (or --groq-key)
 *   - Regulation index JSON built by scripts/regulations/index_32021r0782_da.py
 */
final class RegulationAuditCommand extends BaseCommand
{
    protected string $defaultName = 'regulation_audit';

    // Do not hardcode a Groq model that might be decommissioned; we resolve dynamically from /models.
    private const DEFAULT_MODEL = '';
    private const DEFAULT_MAX_TOKENS = 3500;
    private const MAX_RETRIES = 1;

    private function envAny(array $names): string
    {
        foreach ($names as $n) {
            foreach ([$n, strtolower($n)] as $key) {
                $v = getenv($key);
                if ($v === false || $v === '') {
                    $v = $_ENV[$key] ?? $_SERVER[$key] ?? '';
                }
                if ($v !== '' && $v !== false) {
                    return trim((string)$v);
                }
            }
        }
        return '';
    }

    protected function buildOptionParser(\Cake\Console\ConsoleOptionParser $parser): \Cake\Console\ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->addOptions([
            'groq-key' => [
                'help' => 'Override Groq API key (use quotes).',
            ],
            'groq-model' => [
                'help' => 'Groq model override. If omitted, we auto-pick from /models.',
            ],
            'max-tokens' => [
                'help' => 'Max tokens for the model response (default: 3500).',
            ],
            'limit' => [
                'help' => 'Max number of fixtures to evaluate for evidence (default: 6).',
                'short' => 'l',
            ],
            'save-prompt' => [
                'help' => 'Write the generated prompt to logs for debugging.',
                'boolean' => true,
            ],
            'insecure' => [
                'help' => 'Disable TLS certificate verification (local dev only).',
                'boolean' => true,
            ],
        ]);
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        // Load app config so Groq.apiKey/Groq.model can be set in config/app_local.php
        Configure::load('app', 'default', false);
        @Configure::load('app_local', 'default', true);

        $key = (string)($args->getOption('groq-key') ?? '');
        if ($key === '') {
            $key = $this->envAny(['GROQ_API_KEY', 'GROQ_KEY', 'GROQ_TOKEN', 'GROQ_API_TOKEN']);
        }
        if ($key === '') {
            // Allow storing local keys in config/app_local.php without relying on CLI env vars.
            $cfgKey = (string)(Configure::read('Groq.apiKey') ?? '');
            if ($cfgKey !== '') {
                $key = trim($cfgKey);
            }
        }
        if ($key === '') {
            $io->err('Missing GROQ_API_KEY (or --groq-key).');
            $io->err('Tip: If you configured the key only in Apache vhost, CLI will not see it. Add Groq.apiKey in config/app_local.php or set $env:GROQ_API_KEY before running.');
            return static::CODE_ERROR;
        }
        $model = (string)($args->getOption('groq-model') ?? '');
        if ($model === '') {
            $model = (string)(Configure::read('Groq.model') ?? '');
        }
        if ($model === '') {
            $model = $this->envAny(['GROQ_MODEL']);
        }

        $limit = (int)($args->getOption('limit') ?? 6);
        if ($limit < 1) { $limit = 1; }
        if ($limit > 25) { $limit = 25; }
        $maxTokens = (int)($args->getOption('max-tokens') ?? self::DEFAULT_MAX_TOKENS);
        if ($maxTokens < 512) { $maxTokens = 512; }
        if ($maxTokens > 8192) { $maxTokens = 8192; }

        $client = new Client([
            'timeout' => 60,
            'verify' => !((bool)$args->getOption('insecure')),
        ]);
        $model = $this->resolveGroqModel($client, $key, $model, $io);
        if ($model === '') {
            $io->err('Could not resolve a Groq model. Set --groq-model or GROQ_MODEL.');
            return static::CODE_ERROR;
        }

        // Load regulation chunks (articles 18-20-19 are our main surface)
        $idx = new RegulationIndex();
        $hits = [];
        foreach (['Artikel 18', 'Artikel 19', 'Artikel 20', 'Artikel 12', 'Artikel 9'] as $q) {
            $hits[$q] = array_slice($idx->search($q, 6), 0, 6);
        }

        $io->out('Evaluating sample fixtures for evidence...');
        $repo = new FixtureRepository();
        $fixtures = $repo->getAll();
        $fixtures = array_slice($fixtures, 0, min($limit, count($fixtures)));
        $runner = new ScenarioRunner();
        $evidence = [];
        foreach ($fixtures as $fx) {
            $res = $runner->evaluateFixture($fx);
            $actual = (array)($res['actual'] ?? []);
            $evidence[] = [
                'id' => $fx['id'] ?? 'unknown',
                'profile' => $actual['profile'] ?? null,
                'compensation' => $actual['compensation'] ?? null,
                'refusion' => $actual['refusion'] ?? null,
                'art20_assistance' => $actual['art20_assistance'] ?? null,
                'downgrade' => $actual['downgrade'] ?? null,
                'claim' => $actual['claim'] ?? null,
            ];
        }
        $evidence = $this->compactEvidence($evidence);

        // High-signal code context (short excerpts)
        $root = dirname(__DIR__, 2);
        $codeFiles = [
            'Split flow controller' => $root . '/src/Controller/FlowController.php',
            'Unified pipeline' => $root . '/src/Controller/Api/PipelineController.php',
            'Claim calculator' => $root . '/src/Service/ClaimCalculator.php',
            'Art.18 refusion evaluator' => $root . '/src/Service/Art18RefusionEvaluator.php',
            'Art.20 assistance evaluator' => $root . '/src/Service/Art20AssistanceEvaluator.php',
            'Downgrade evaluator' => $root . '/src/Service/DowngradeEvaluator.php',
        ];
        $codeSnips = [];
        foreach ($codeFiles as $label => $path) {
            $raw = is_file($path) ? (string)file_get_contents($path) : '';
            if ($raw === '') { continue; }
            // keep it short-ish to reduce token burn
            $codeSnips[] = "### {$label}: " . str_replace('\\', '/', $path) . "\n" . $this->truncate($raw, 3500);
        }

        // Include TRIN 1-9 templates (UI content) so the audit can review wording and conditional blocks.
        $tplMap = [
            'TRIN 1 start' => $root . '/templates/Flow/start.php',
            'TRIN 2 entitlements (Upload billet)' => $root . '/templates/Flow/entitlements.php',
            'TRIN 3 journey' => $root . '/templates/Flow/journey.php',
            'TRIN 4 incident' => $root . '/templates/Flow/incident.php',
            'TRIN 5 choices (Art.20 transport)' => $root . '/templates/Flow/choices.php',
            'TRIN 6 remedies (Art.18)' => $root . '/templates/Flow/remedies.php',
            'TRIN 7 assistance (Art.20 udgifter)' => $root . '/templates/Flow/assistance.php',
            'TRIN 8 downgrade' => $root . '/templates/Flow/downgrade.php',
            'TRIN 9 compensation (Art.19)' => $root . '/templates/Flow/compensation.php',
        ];
        $tplSnips = [];
        foreach ($tplMap as $label => $path) {
            $raw = is_file($path) ? (string)file_get_contents($path) : '';
            if ($raw === '') { continue; }
            $tplSnips[] = "### TEMPLATE {$label}: " . str_replace('\\', '/', $path) . "\n" . $this->truncate($raw, 3500);
        }

        $prompt = $this->buildPrompt($hits, $evidence, $codeSnips, $tplSnips);
        if (!is_dir(LOGS)) { @mkdir(LOGS, 0775, true); }
        if ((bool)$args->getOption('save-prompt')) {
            $pPath = rtrim(LOGS, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'regulation_audit_prompt_' . date('Ymd_His') . '.txt';
            file_put_contents($pPath, $prompt);
            $io->out('Wrote prompt: ' . $pPath);
        }

        $io->out('Calling Groq...');
        $text = $this->callGroqWithRetries($client, $key, $model, $maxTokens, $prompt, $io);
        if ($text === '') { return static::CODE_ERROR; }

        $outPath = rtrim(LOGS, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'regulation_audit_' . date('Ymd_His') . '.md';
        file_put_contents($outPath, $text);
        $io->out('Wrote: ' . $outPath);
        return static::CODE_SUCCESS;
    }

    /**
     * @param array<string,mixed> $hits
     * @param array<int,array<string,mixed>> $evidence
     * @param array<int,string> $codeSnips
     */
    private function buildPrompt(array $hits, array $evidence, array $codeSnips, array $tplSnips): string
    {
        $lines = [];
        $lines[] = "# Regulation Audit Context";
        $lines[] = "We implement a split wizard flow with steps 1-9 (start..compensation) and a unified API pipeline used by demo scenarios.";
        $lines[] = "";
        $lines[] = "## Regulation Extracts (search hits; quote IDs can be used later)";
        foreach ($hits as $q => $rows) {
            $lines[] = "### {$q}";
            foreach ((array)$rows as $r) {
                $id = (string)($r['id'] ?? '');
                $art = (int)($r['article'] ?? 0);
                $pf = (int)($r['page_from'] ?? 0);
                $pt = (int)($r['page_to'] ?? 0);
                $txt = (string)($r['text'] ?? '');
                $lines[] = "- {$id} (Art {$art}, p{$pf}-{$pt}): " . $this->truncate($txt, 500);
            }
            $lines[] = "";
        }
        $lines[] = "## Evidence From Fixtures (actual outputs)";
        $lines[] = json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $lines[] = "";
        $lines[] = "## Code Context";
        $lines[] = implode("\n\n", $codeSnips);
        $lines[] = "";
        $lines[] = "## UI Templates (TRIN 1-9)";
        $lines[] = "These templates contain the exact wording, labels, helper texts, and conditional blocks shown to users.";
        $lines[] = "Audit both correctness (legal alignment) and clarity (user comprehension).";
        $lines[] = implode("\n\n", $tplSnips);
        $lines[] = "";
        $lines[] = "## Task";
        $lines[] = "Audit our implementation against the regulation. Focus on Articles 18, 19, 20, plus exceptions (19(10), thresholds, refund exclusion).";
        $lines[] = "IMPORTANT:";
        $lines[] = "- Do NOT output placeholders like '...', 'TBD', or 'TODO'. Fill every field with concrete content.";
        $lines[] = "- Provide at least 3 findings across the report (preferably more).";
        $lines[] = "- Include at least 1 finding in High OR state explicitly 'None' under that severity.";
        $lines[] = "Output format (STRICT markdown headings):";
        $lines[] = "- # Summary";
        $lines[] = "- # Table of Contents (bulleted list with anchors to headings)";
        $lines[] = "- # Findings";
        $lines[] = "  - ## High";
        $lines[] = "  - ## Medium";
        $lines[] = "  - ## Low";
        $lines[] = "For each finding, use this template:";
        $lines[] = "- ### <short title>";
        $lines[] = "- Regulation: reference the relevant Article(s) and include 1-2 quote IDs from the index (e.g., art18_p18_c1).";
        $lines[] = "- Evidence: mention specific keys/steps/files from the provided context.";
        $lines[] = "- Risk/Gaps: concrete.";
        $lines[] = "- Recommendation: specific implementation change (where to change it).";
        $lines[] = "- Affected steps/keys: TRIN + key paths (e.g., flow.form.remedyChoice, wizard.step6_remedies.*).";
        $lines[] = "";
        $lines[] = "# TRIN 1-9 Review";
        $lines[] = "Briefly review each TRIN (1..9) UI wording: what is good vs unclear, and 1-3 suggested rewrites if needed.";
        return implode("\n", $lines);
    }

    private function truncate(string $s, int $limit): string
    {
        $s = trim($s);
        if (strlen($s) <= $limit) { return $s; }
        return substr($s, 0, $limit) . "\n/* ...truncated... */";
    }

    /**
     * Remove null/empty values and trim long strings to keep prompts within context.
     *
     * @param array<int,array<string,mixed>> $evidence
     * @return array<int,array<string,mixed>>
     */
    private function compactEvidence(array $evidence): array
    {
        $out = [];
        foreach ($evidence as $row) {
            if (!is_array($row)) { continue; }
            $clean = $this->compactValue($row);
            if (is_array($clean)) { $out[] = $clean; }
        }
        return $out;
    }

    /**
     * @param mixed $v
     * @return mixed
     */
    private function compactValue($v)
    {
        if (is_string($v)) {
            $v = trim($v);
            if ($v === '') { return null; }
            if (strlen($v) > 5000) { return substr($v, 0, 5000) . '/*...truncated...*/'; }
            return $v;
        }
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $vv) {
                $c = $this->compactValue($vv);
                if ($c === null) { continue; }
                $out[$k] = $c;
            }
            if ($out === []) { return null; }
            return $out;
        }
        if ($v === null) { return null; }
        return $v;
    }

    private function callGroqWithRetries(Client $client, string $key, string $model, int $maxTokens, string $prompt, ConsoleIo $io): string
    {
        $system = "You are a strict EU rail passenger rights compliance auditor. Be concrete, cite evidence, and list gaps as actionable items. Output MUST be markdown only.";
        $attempt = 0;
        $last = '';
        while (true) {
            $attempt++;
            try {
                $resp = $client->post('https://api.groq.com/openai/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $key,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => $model,
                        'temperature' => 0.2,
                        'max_tokens' => $maxTokens,
                        'messages' => [
                            ['role' => 'system', 'content' => $system],
                            ['role' => 'user', 'content' => $prompt],
                        ],
                    ],
                ]);
                $json = json_decode((string)$resp->getBody(), true);
                $text = (string)($json['choices'][0]['message']['content'] ?? '');
                $text = $this->normalizeAuditMarkdown($text);
                $last = $text;
            } catch (\Throwable $e) {
                $io->err('Groq call failed: ' . $e->getMessage());
                return '';
            }

            if ($this->isReportSufficient($last)) {
                return $last;
            }

            if ($attempt > self::MAX_RETRIES + 1) {
                $io->warning('Groq output did not meet the required structure; writing best-effort output.');
                return $last;
            }

            $io->warning('Groq output was missing required sections or contained placeholders; retrying once with stricter instruction...');
            $prompt = $this->buildRepairPrompt($prompt, $last);
        }
    }

    private function buildRepairPrompt(string $originalPrompt, string $badOutput): string
    {
        return
            "You produced an incomplete report.\n\n" .
            "Rewrite the report to fully match the required markdown structure from the Task section, and replace all placeholders like '...' with concrete content.\n" .
            "Do not invent evidence; reference the provided context.\n\n" .
            "BAD OUTPUT TO REWRITE:\n\n" .
            "```md\n" . $this->truncate($badOutput, 4000) . "\n```\n\n" .
            "ORIGINAL CONTEXT (use this):\n\n" .
            $originalPrompt;
    }

    private function normalizeAuditMarkdown(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') { return ''; }

        $lines = explode("\n", $text);
        $out = [];
        foreach ($lines as $ln) {
            $t = ltrim($ln);
            // Common mistake: list item containing a heading
            if (str_starts_with($t, '- ### ')) {
                $out[] = '### ' . substr($t, 6);
                continue;
            }
            if ($t === 'Findings') { $out[] = '# Findings'; continue; }
            if ($t === 'Summary') { $out[] = '# Summary'; continue; }
            if ($t === 'Table of Contents') { $out[] = '# Table of Contents'; continue; }
            if ($t === 'High') { $out[] = '## High'; continue; }
            if ($t === 'Medium') { $out[] = '## Medium'; continue; }
            if ($t === 'Low') { $out[] = '## Low'; continue; }
            $out[] = $ln;
        }
        return trim(implode("\n", $out)) . "\n";
    }

    private function isReportSufficient(string $text): bool
    {
        $t = strtolower($text);
        foreach (['# summary', '# findings', '## high', '## medium', '## low'] as $needle) {
            if (!str_contains($t, $needle)) { return false; }
        }
        // must have at least a couple of findings headings
        if (substr_count($text, "\n### ") < 2) { return false; }
        // reject obvious placeholder content
        if (preg_match('/Risk\\s*\\/\\s*Gaps:\\s*\\.\\.\\./i', $text)) { return false; }
        if (preg_match('/Recommendation:\\s*\\.\\.\\./i', $text)) { return false; }
        return true;
    }

    private function resolveGroqModel(Client $client, string $key, string $requestedModel, ConsoleIo $io): string
    {
        $requestedModel = trim($requestedModel);
        $ids = $this->fetchGroqModelIds($client, $key, $io);
        if ($ids === []) {
            // No model listing available; fall back to whatever was requested (or a sane default).
            if ($requestedModel !== '') { return $requestedModel; }
            return 'llama-3.3-70b-versatile';
        }

        if ($requestedModel !== '' && in_array($requestedModel, $ids, true)) {
            return $requestedModel;
        }
        if ($requestedModel !== '' && !in_array($requestedModel, $ids, true)) {
            $io->warning("Groq model '{$requestedModel}' not found in /models; selecting a supported model instead.");
        }

        $picked = $this->pickGroqModelFromIds($ids);
        if ($picked === '' && $requestedModel !== '') { return $requestedModel; }
        if ($picked === '') { $picked = $ids[0]; }
        $io->out('Using Groq model: ' . $picked);
        return $picked;
    }

    /**
     * @return string[]
     */
    private function fetchGroqModelIds(Client $client, string $key, ConsoleIo $io): array
    {
        try {
            $resp = $client->get('https://api.groq.com/openai/v1/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type' => 'application/json',
                ],
            ]);
            $json = json_decode((string)$resp->getBody(), true);
            $rows = (array)($json['data'] ?? []);
            $ids = [];
            foreach ($rows as $r) {
                if (!is_array($r)) { continue; }
                $id = trim((string)($r['id'] ?? ''));
                if ($id !== '') { $ids[] = $id; }
            }
            $ids = array_values(array_unique($ids));
            return $ids;
        } catch (\Throwable $e) {
            $io->warning('Could not list Groq models: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Prefer large, general chat models; avoid obvious non-chat models.
     *
     * @param string[] $ids
     */
    private function pickGroqModelFromIds(array $ids): string
    {
        $candidates = [];
        foreach ($ids as $id) {
            $lid = strtolower($id);
            // Exclude common non-chat families.
            if (str_contains($lid, 'whisper') || str_contains($lid, 'embedding') || str_contains($lid, 'tts')) { continue; }
            $candidates[] = $id;
        }
        if ($candidates === []) { return ''; }

        $score = function(string $id): int {
            $lid = strtolower($id);
            $s = 0;
            // Prefer the same family used in the web flow if present.
            if (str_contains($lid, 'gpt-oss-120b')) { $s += 500; }
            if (str_contains($lid, 'llama-3.3-70b')) { $s += 450; }
            if (str_contains($lid, 'llama-3.1-70b')) { $s += 420; }
            if (preg_match('/\\b120b\\b/', $lid)) { $s += 120; }
            if (preg_match('/\\b70b\\b/', $lid)) { $s += 70; }
            if (preg_match('/\\b32k\\b|\\b32768\\b/', $lid)) { $s += 30; }
            if (str_contains($lid, 'versatile')) { $s += 10; }
            return $s;
        };

        usort($candidates, static fn($a, $b) => $score($b) <=> $score($a));
        return $candidates[0] ?? '';
    }
}
