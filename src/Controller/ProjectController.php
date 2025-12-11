<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

class ProjectController extends AppController
{
    /**
     * Map friendly slugs to base filenames (without extension) expected in webroot.
     * @var array<string,string>
     */
    private array $fileMap = [
        'forklaring' => 'forklaring_af_flow_chart_v_2',
        'flowchart' => 'flow_chart_med_steps_med_undtagelser_indarbejdet_v_4',
    // The actual file may be named with spaces or literal %20; we generate both variants in candidateNames().
    // Existing file in repo uses 'reimboursement%20form%20-%20EN%20-%20accessible.pdf'.
    'form' => 'reimboursement form - EN - accessible',
        'regulation' => 'CELEX_32021R0782_DA_TXT',
    ];

    /**
     * Titles to display for each slug.
     * @var array<string,string>
     */
    private array $titleMap = [
        'forklaring' => 'Forklaring af flow chart (v2)',
        'flowchart' => 'Flow chart med steps og undtagelser (v4)',
        'form' => 'Reimbursement form – EN – accessible',
        'regulation' => 'Forordning: CELEX 32021R0782 (DA)'
    ];

    /**
     * Allowed file extensions we'll try to locate for each base filename.
     * Order matters for preference.
     *
     * @var string[]
     */
    private array $allowedExtensions = [
        'pdf', 'html', 'htm', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt', 'docx'
    ];

    /**
     * Additional subfolders under webroot we will search in.
     * @var string[]
     */
    private array $searchDirs = ['', 'files', 'docs'];

    public function index(): void
    {
        $items = [];
        foreach (['forklaring','flowchart','form','regulation'] as $slug) {
            $items[] = [
                'slug' => $slug,
                'title' => $this->titleMap[$slug] ?? ucfirst($slug),
            ];
        }
        $this->set('items', $items);
    }

    /**
     * Consolidated links page with UI, demo, and API endpoints for quick testing.
     */
    public function links(): void
    {
        $groups = [
            [
                'name' => 'Demo / Mocks',
                'links' => [
                    ['title' => 'Fixtures', 'href' => '/api/demo/fixtures', 'method' => 'GET', 'desc' => 'Liste over demo fixtures (JSON).'],
                    ['title' => 'Exemption Fixtures', 'href' => '/api/demo/exemption-fixtures', 'method' => 'GET', 'desc' => 'Eksempler p? undtagelsesprofiler (JSON).'],
                    ['title' => 'Art. 12 Fixtures', 'href' => '/api/demo/art12-fixtures', 'method' => 'GET', 'desc' => 'Scenarier for gennemg?ende billet (JSON).'],
                    ['title' => 'Scenarios (list)', 'href' => '/api/demo/scenarios', 'method' => 'GET', 'desc' => 'Opsummerede scenarier til test.'],
                    ['title' => 'Scenarios (with eval)', 'href' => '/api/demo/scenarios?withEval=1', 'method' => 'GET', 'desc' => 'Scenarier med beregnet profil + Art.12/9 (JSON).'],
                    ['title' => 'Run Scenarios', 'href' => '/api/demo/run-scenarios', 'method' => 'GET', 'desc' => 'K?rer scenarierne og returnerer resultater.'],
                    ['title' => 'Analyze Mock Tickets', 'href' => '/api/demo/mock-tickets', 'method' => 'GET', 'desc' => 'Scanner mocks/tests/fixtures og k?rer fuld analyse (Art. 12/9, refund, refusion, claim).'],
                    ['title' => 'Analyze Mock Tickets + RNE', 'href' => '/api/demo/mock-tickets?withRne=1', 'method' => 'GET', 'desc' => 'Som ovenfor, men med RNE-enrichment sl?et til.'],
                ],
            ],
            [
                'name' => 'Provider Stubs',
                'links' => [
                    ['title' => 'SNCF Realtime', 'href' => '/api/providers/sncf/realtime', 'method' => 'GET', 'desc' => 'Stub for SNCF realtidsdata.'],
                    ['title' => 'SNCF Trains', 'href' => '/api/providers/sncf/trains', 'method' => 'GET', 'desc' => 'Stub for SNCF togliste.'],
                    ['title' => 'SNCF Booking Validate', 'href' => '/api/providers/sncf/booking/validate', 'method' => 'GET', 'desc' => 'Stub for SNCF bookingvalidering.'],
                    ['title' => 'DB Realtime', 'href' => '/api/providers/db/realtime', 'method' => 'GET', 'desc' => 'Stub for DB realtidsdata.'],
                    ['title' => 'DB Trip', 'href' => '/api/providers/db/trip', 'method' => 'GET', 'desc' => 'Stub for DB rejseopslag.'],
                    ['title' => 'DB Lookup', 'href' => '/api/providers/db/lookup', 'method' => 'GET', 'desc' => 'Stub for DB lookup.'],
                    ['title' => 'DSB Realtime', 'href' => '/api/providers/dsb/realtime', 'method' => 'GET', 'desc' => 'Stub for DSB realtidsdata.'],
                    ['title' => 'DSB Trip', 'href' => '/api/providers/dsb/trip', 'method' => 'GET', 'desc' => 'Stub for DSB rejseopslag.'],
                    ['title' => 'RNE Realtime', 'href' => '/api/providers/rne/realtime', 'method' => 'GET', 'desc' => 'Stub for RNE realtidsdata (bruges ogs? i demo analyzer).'],
                    ['title' => 'Open RT', 'href' => '/api/providers/open/rt', 'method' => 'GET', 'desc' => '?ben generisk realtime stub.'],
                ],
            ],
            [
                'name' => 'OCR / Ingest Stub',
                'links' => [
                    ['title' => 'Ingest Ticket (stub)', 'href' => '/api/ingest/ticket', 'method' => 'POST', 'note' => 'Optional JSON/form', 'desc' => 'OCR/indl?sning stub: returnerer journey-skelet og logs.'],
                    ['title' => 'Unified Pipeline (all-in-one)', 'href' => '/api/pipeline/run', 'method' => 'POST', 'note' => 'JSON', 'desc' => 'Samler ingest + Exemptions + Art.12/9 + kompensation + refund/refusion + claim.'],
                ],
            ],
        ];

        $this->set(compact('groups'));
        $this->viewBuilder()->setTemplate('links');
    }

    /**
     * Extended links page with v2 demo/fixture runner endpoints and current flow/wizard URLs.
     */
    public function linksV2(): void
    {
        $groups = [
            [
                'name' => 'Client flows / UI',
                'links' => [
                    ['title' => 'Flow (single-page)', 'href' => '/flow/one', 'method' => 'GET', 'desc' => 'Legacy ONE-page wizard (ikke primær).'],
                    ['title' => 'Flow (split steps)', 'href' => '/flow/start', 'method' => 'GET', 'desc' => 'PRIMÆR: trinvis wizard (trin 1-6).'],
                    ['title' => 'Wizard (legacy MVP)', 'href' => '/wizard', 'method' => 'GET', 'desc' => 'Legacy checkout-flow med eget variabelsæt (ikke i aktiv brug).'],
                    ['title' => 'Upload (OCR entry)', 'href' => '/upload', 'method' => 'GET', 'desc' => 'Ingest-demo; output mappes ikke 1:1 til split-steps state.'],
                    ['title' => 'Claims start (dev)', 'href' => '/claims', 'method' => 'GET', 'desc' => 'Dev-inspektionsview af komp/refusion; ikke bundet til split-steps.'],
                    ['title' => 'Reimbursement demo', 'href' => '/reimbursement', 'method' => 'GET', 'desc' => 'Demo af PDF/official form; egen state (ikke split-steps).'],
                ],
            ],
            [
                'name' => 'Demo v2 (fixtures & scenarios)',
                'links' => [
                    ['title' => 'Fixtures (v2 list)', 'href' => '/api/demo/v2/fixtures', 'method' => 'GET', 'desc' => 'Liste over v2-fixtures (tests/fixtures/demo).'],
                    ['title' => 'Scenarios (v2)', 'href' => '/api/demo/v2/scenarios', 'method' => 'GET', 'desc' => 'Vis fixtures; append ?withEval=1 for runtime eval.'],
                    ['title' => 'Run Scenarios (v2)', 'href' => '/api/demo/v2/run-scenarios', 'method' => 'GET', 'desc' => 'Testrunner; ?id= eller ?limit= for subset.'],
                    ['title' => 'Dump Session → Fixture', 'href' => '/api/demo/v2/dump-session?asFixture=1', 'method' => 'GET', 'desc' => 'Eksporter aktuel flow-session som v2-fixture skeleton.'],
                ],
            ],
            [
                'name' => 'Demo legacy (v1 endpoints)',
                'links' => [
                    ['title' => 'Fixtures', 'href' => '/api/demo/fixtures', 'method' => 'GET', 'desc' => 'Legacy fixtures (JSON).'],
                    ['title' => 'Exemption Fixtures', 'href' => '/api/demo/exemption-fixtures', 'method' => 'GET', 'desc' => 'Undtagelsesprofiler (JSON).'],
                    ['title' => 'Art. 12 Fixtures', 'href' => '/api/demo/art12-fixtures', 'method' => 'GET', 'desc' => 'Gennemgående billet scenarier.'],
                    ['title' => 'Scenarios (list)', 'href' => '/api/demo/scenarios', 'method' => 'GET', 'desc' => 'Legacy scenarios.'],
                    ['title' => 'Scenarios (with eval)', 'href' => '/api/demo/scenarios?withEval=1', 'method' => 'GET', 'desc' => 'Legacy scenarios med beregning.'],
                    ['title' => 'Run Scenarios', 'href' => '/api/demo/run-scenarios', 'method' => 'GET', 'desc' => 'Legacy testrunner.'],
                    ['title' => 'Analyze Mock Tickets', 'href' => '/api/demo/mock-tickets', 'method' => 'GET', 'desc' => 'Scan mocks/tests/fixtures og kør fuld analyse.'],
                    ['title' => 'Analyze Mock Tickets + RNE', 'href' => '/api/demo/mock-tickets?withRne=1', 'method' => 'GET', 'desc' => 'Som ovenfor med RNE enrichment.'],
                ],
            ],
            [
                'name' => 'Ingest / Pipeline',
                'links' => [
                    ['title' => 'Ingest Ticket (stub)', 'href' => '/api/ingest/ticket', 'method' => 'POST', 'note' => 'JSON/form', 'desc' => 'OCR/ingest stub der returnerer journey + logs.'],
                    ['title' => 'Unified Pipeline', 'href' => '/api/pipeline/run', 'method' => 'POST', 'note' => 'JSON', 'desc' => 'Kører ingest + Art12/9/18/19 + claim i ét kald.'],
                    ['title' => 'Compute: Compensation (Art.19)', 'href' => '/api/compute/compensation', 'method' => 'POST', 'note' => 'JSON', 'desc' => 'EU kompensation (Art. 19) beregning.'],
                    ['title' => 'Compute: Exemptions', 'href' => '/api/compute/exemptions', 'method' => 'POST', 'note' => 'JSON', 'desc' => 'Undtagelsesprofil (scope/blocked).'],
                    ['title' => 'Compute: Art.12', 'href' => '/api/compute/art12', 'method' => 'POST', 'note' => 'JSON', 'desc' => 'Gennemgående billet evaluator.'],
                    ['title' => 'Compute: Art.9', 'href' => '/api/compute/art9', 'method' => 'POST', 'note' => 'JSON', 'desc' => 'Information forpligtelser.'],
                    ['title' => 'Compute: Refund (Art.18-like)', 'href' => '/api/compute/refund', 'method' => 'POST', 'note' => 'JSON', 'desc' => 'Refund evaluator.'],
                    ['title' => 'Compute: Refusion (Art.18)', 'href' => '/api/compute/refusion', 'method' => 'POST', 'note' => 'JSON', 'desc' => 'Rerouting/assistance evaluator.'],
                    ['title' => 'Compute: Claim (unified)', 'href' => '/api/compute/claim', 'method' => 'POST', 'note' => 'JSON', 'desc' => 'Samlet kravberegning.'],
                ],
            ],
            [
                'name' => 'Provider stubs',
                'links' => [
                    ['title' => 'SNCF Realtime', 'href' => '/api/providers/sncf/realtime', 'method' => 'GET'],
                    ['title' => 'SNCF Trains', 'href' => '/api/providers/sncf/trains', 'method' => 'GET'],
                    ['title' => 'SNCF Booking Validate', 'href' => '/api/providers/sncf/booking/validate', 'method' => 'GET'],
                    ['title' => 'DB Realtime', 'href' => '/api/providers/db/realtime', 'method' => 'GET'],
                    ['title' => 'DB Trip', 'href' => '/api/providers/db/trip', 'method' => 'GET'],
                    ['title' => 'DB Lookup', 'href' => '/api/providers/db/lookup', 'method' => 'GET'],
                    ['title' => 'DSB Realtime', 'href' => '/api/providers/dsb/realtime', 'method' => 'GET'],
                    ['title' => 'DSB Trip', 'href' => '/api/providers/dsb/trip', 'method' => 'GET'],
                    ['title' => 'RNE Realtime', 'href' => '/api/providers/rne/realtime', 'method' => 'GET'],
                    ['title' => 'Open RT', 'href' => '/api/providers/open/rt', 'method' => 'GET'],
                ],
            ],
        ];

        $this->set(compact('groups'));
        $this->viewBuilder()->setTemplate('links');
    }

    /**
     * Flow QA: quick links for split-steps, fixture dump, and v2 runners.
     * Aimed at beginners: step-by-step instructions on how to create and run tests.
     */
    public function flowQa(): void
    {
        $this->set('baseUrl', $this->request->getUri()->getScheme() . '://' . $this->request->getUri()->getHost() . ($this->request->getUri()->getPort() ? ':' . $this->request->getUri()->getPort() : '') . $this->request->getAttribute('webroot'));
        $this->viewBuilder()->setTemplate('flow_qa');
    }

    /**
     * Mobile QA: quick overview for Flutter app + shadow tracker endpoints.
     */
    public function mobileQa(): void
    {
        $baseUrl = $this->request->getUri()->getScheme() . '://' . $this->request->getUri()->getHost() . ($this->request->getUri()->getPort() ? ':' . $this->request->getUri()->getPort() : '') . $this->request->getAttribute('webroot');
        $this->set(compact('baseUrl'));
        $this->viewBuilder()->setTemplate('mobile_qa');
    }

    /**
     * View a single asset by slug. If an embeddable type is found, render a template;
     * otherwise, offer a safe file download.
     */
    public function view(string $slug): Response|string|null
    {
        $slug = strtolower($slug);
        if (!isset($this->fileMap[$slug])) {
            throw new NotFoundException('Ukendt side');
        }

        $base = $this->fileMap[$slug];
        $found = $this->locateAsset($base);

        if ($found === null) {
            // Render view with a helpful notice instead of 404, to guide adding files
            $title = $this->titleMap[$slug] ?? 'Dokument';
            $this->set(compact('slug', 'base', 'title'));
            $this->set('fileInfo', null);
            $this->viewBuilder()->setTemplate('view');
            return null;
        }

        // If it's a known embeddable type, render template; else force download
        $ext = strtolower(pathinfo($found['webPath'], PATHINFO_EXTENSION));
        $embeddable = in_array($ext, ['pdf', 'html', 'htm', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'], true);

        if ($embeddable) {
            $title = $this->titleMap[$slug] ?? 'Dokument';
            $this->set('fileInfo', $found);
            $this->set(compact('slug', 'base', 'title'));
            $this->viewBuilder()->setTemplate('view');
            return null;
        }

        // Fall back to a download response for non-embeddable content
        return $this->response->withFile(
            $found['fsPath'],
            ['download' => true, 'name' => basename($found['fsPath'])]
        );
    }

    /**
     * Generate an annotated version of a project PDF by appending a developer-notes page.
     * Currently supports 'forklaring' (forklaring_af_flow_chart_v_2.pdf) and appends the
     * Step Rail Exemptions (Art. 2) developer notes.
     */
    public function annotate(string $slug): ?Response
    {
        $slug = strtolower($slug);
        if (!isset($this->fileMap[$slug])) {
            throw new NotFoundException('Ukendt side');
        }
        $base = $this->fileMap[$slug];
        $found = $this->locateAsset($base);
        if ($found === null) {
            throw new NotFoundException('Filen blev ikke fundet');
        }

        // Minimal FPDI append: if fpdi is available, append a text page
        if (!class_exists('setasign\\Fpdi\\Fpdi')) {
            return $this->response->withFile($found['fsPath']);
        }
        $notes = $this->getRailExemptionsNotes();
        $srcPath = $found['fsPath'];
        $pdf = new \setasign\Fpdi\Fpdi('P','mm','A4');
        try {
            $pageCount = $pdf->setSourceFile($srcPath);
            for ($i = 1; $i <= $pageCount; $i++) {
                $tpl = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);
            }
        } catch (\Throwable $e) {
            // If import fails (e.g., compressed xref), just generate notes-only PDF
        }
        // Append notes page
        $pdf->AddPage('P', 'A4');
        $pdf->SetFont('Helvetica','B',12);
        $pdf->MultiCell(0, 7, 'Developer Notes: Step Rail Exemptions (Art. 2, 2021/782)');
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica','',9);
        // Split notes into manageable lines
        $lines = preg_split('/\r?\n/', $notes) ?: [];
        foreach ($lines as $line) {
            $pdf->MultiCell(0, 5, $line);
        }

        $out = $pdf->Output('S');
        return $this->response->withType('pdf')->withStringBody($out);
    }

    /**
     * Returns the developer-note content provided by the user for Step Rail Exemptions.
     */
    private function getRailExemptionsNotes(): string
    {
        return (string)<<<'TXT'
Step Rail Exemptions (Art. 2, 2021/782)

Formål: Afgøre hvilke artikler (12, 18(3), 19, 20(2), 30(2), 10) der gælder/er fritaget per rejse/segment.

Primære regler (kort):
- Art. 2(6)(a): Mulig fritagelse for by/forstad og regional trafik.
- Art. 2(6)(b): Intl trafik med betydelig del og ≥1 stop uden for EU kan fritages.
- Art. 2(4): Long-distance domestic undtagelser frem til 3. dec. 2029.
- Art. 2(5): Art. 10 kan fritages til 7. juni 2030 (teknisk umulighed).
- Art. 2(8): Visse artikler gælder fortsat selv ved regional/urban fritagelse (fx Art. 5, 11, 13, 14, 21, 22, 27, 28).

Input (auto fra tidligere steps): journey_segments, distance_km, is_domestic/is_international, ticket_scope, seller_type, country_exemptions.

Output example:
exemption_profile = {
  scope: regional|long_domestic|intl_inside_eu|intl_beyond_eu,
  articles: { art12, art18_3, art19, art20_2, art30_2, art10 },
  notes: [...], ui_banners: [...]
};

Beslutningslogik (kort):
1) Klassificér tjenesten (regional/long-domestic/international; evaluer intl_beyond_EU med tærskel for "betydelig del").
2) Per-segment national lookup i country_exemptions og slå undtagelser til/fra for artiklerne.
3) Art. 10: hvis land i (AT, HR, HU, LV, PL, RO) → art10=false til 7/6/2030 (+ banner).
4) Art. 12: hvis undtaget → art12=false, disable gennemgående ansvar (+ banner).
5) Art. 18(3): hvis undtaget → art18_3=false (+ banner).
6) Art. 19 og 20(2): sæt false hvor undtaget; kompensation/assistance falder tilbage til nationale ordninger.
7) Art. 30(2): deaktiver hvor national fritagelse gælder; vis kun egen tekst.

Edge cases: blandet rute (aggregér strengeste undtagelser), Art. 10 undtaget (brug ikke-live RNE + bilagsupload), Art. 12 undtaget (split pr. billet), SE<150 km, FI commuter m.v.

Pseudo-kode (kort):
const prof = defaultAllTrueProfile(classifyScope(journey));
for (seg of journey.segments) { cx = matrix.lookup(seg); apply(cx, prof); }
if (isIntlBeyondEU(journey)) applyIntlExemptions(prof);
addUiBanners(prof);
return prof;
TXT;
    }

    /**
     * Attempt to extract the text of a PDF for quick reading/summarizing.
     * Requires smalot/pdfparser if available; otherwise shows a helpful notice.
     */
    public function text(string $slug): ?string
    {
        $slug = strtolower($slug);
        if (!isset($this->fileMap[$slug])) {
            throw new NotFoundException('Ukendt side');
        }

        $title = $this->titleMap[$slug] ?? 'Dokumenttekst';
        $base = $this->fileMap[$slug];
        $found = $this->locateAsset($base);
        if ($found === null) {
            $this->set(compact('slug', 'base', 'title'));
            $this->set('textContent', null);
            $this->set('parserAvailable', class_exists('Smalot\\PdfParser\\Parser'));
            $this->viewBuilder()->setTemplate('text');
            return null;
        }

        $text = null;
        $parserAvailable = class_exists('Smalot\\PdfParser\\Parser');
        if ($parserAvailable) {
            try {
                $parserClass = 'Smalot\\PdfParser\\Parser';
                /** @var object $parser */
                $parser = new $parserClass();
                $pdf = $parser->parseFile($found['fsPath']);
                $text = $pdf->getText();
            } catch (\Throwable $e) {
                $text = null; // Fall back to null, show error in view
            }
        }

        $this->set('textContent', $text);
        $this->set('parserAvailable', $parserAvailable);
        $this->set(compact('slug', 'base', 'title'));
        $this->viewBuilder()->setTemplate('text');
        return null;
    }

    /**
     * Try to locate an asset in webroot (optionally under subfolders) with any allowed extension.
     * Returns null if not found.
     *
     * @param string $baseName
     * @return array{fsPath:string, webPath:string}|null
     */
    private function locateAsset(string $baseName): ?array
    {
        $baseCandidates = $this->candidateNames($baseName);
        foreach ($this->searchDirs as $dir) {
            foreach ($baseCandidates as $base) {
                foreach ($this->allowedExtensions as $ext) {
                    $fsPath = rtrim(WWW_ROOT . ($dir !== '' ? $dir . DIRECTORY_SEPARATOR : ''), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $base . '.' . $ext;
                    if (is_file($fsPath)) {
                        // Avoid double-encoding: if base already contains percent-encoding, keep as-is
                        $webBase = str_contains($base, '%') ? $base : rawurlencode($base);
                        $webPath = '/' . ($dir !== '' ? $dir . '/' : '') . $webBase . '.' . $ext;
                        return [
                            'fsPath' => $fsPath,
                            'webPath' => $webPath,
                        ];
                    }
                }
            }
        }
        // Also try matching files that have the exact base name without extension (rare)
        foreach ($this->searchDirs as $dir) {
            foreach ($baseCandidates as $base) {
                $fsPath = rtrim(WWW_ROOT . ($dir !== '' ? $dir . DIRECTORY_SEPARATOR : ''), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $base;
                if (is_file($fsPath)) {
                    $webBase = str_contains($base, '%') ? $base : rawurlencode($base);
                    $webPath = '/' . ($dir !== '' ? $dir . '/' : '') . $webBase;
                    return [
                        'fsPath' => $fsPath,
                        'webPath' => $webPath,
                    ];
                }
            }
        }
        return null;
    }

    /**
     * Produce a set of reasonable filename variants for lookup, handling spaces, underscores and %20.
     */
    private function candidateNames(string $name): array
    {
        $variants = [];
        $add = function (string $v) use (&$variants): void {
            $v = trim($v);
            if ($v !== '' && !in_array($v, $variants, true)) {
                $variants[] = $v;
            }
        };

        $add($name);
    $add(urldecode($name));
        $add(str_replace('%20', ' ', $name));
        $add(str_replace(' ', '%20', $name)); // literal %20 in filename

        // underscore/space variants
        $space = preg_replace('/\s+/', ' ', $name) ?? $name;
        $underscore = str_replace(' ', '_', $space);
        $add($space);
        $add($underscore);
        $add(str_replace('_', ' ', $name));

        // Case variants (in case files were saved in different casing)
        $add(strtolower($name));
        $add(strtoupper($name));

        return $variants;
    }
}
