/**
 * Controllers and Templates export
 * Generated: 2025-10-11T16:07:31+00:00
 * Files: 50
 */

===== FILE: src\Controller\Admin\ClaimsController.php =====

<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;

class ClaimsController extends AppController
{
    public function index(): void
    {
        $claims = $this->fetchTable('Claims')->find()->orderDesc('created')->all();
        $this->set(compact('claims'));
    }

    public function view(string $id): void
    {
        $claim = $this->fetchTable('Claims')->get($id);
        $this->set(compact('claim'));
    }

    public function updateStatus(string $id): void
    {
        $this->request->allowMethod(['post']);
        $claims = $this->fetchTable('Claims');
        $claim = $claims->get($id);
        $claim->status = (string)$this->request->getData('status');
        $claim->notes = (string)($this->request->getData('notes') ?? $claim->notes);
        $claims->save($claim);
        $this->redirect(['action' => 'view', $id]);
        return; // stop execution without returning a Response
    }

    public function markPaid(string $id): void
    {
        $this->request->allowMethod(['post']);
        $claims = $this->fetchTable('Claims');
        $claim = $claims->get($id);
        $claim->payout_status = 'paid';
        $claim->payout_reference = (string)($this->request->getData('payout_reference') ?? '');
        $claim->paid_at = date('Y-m-d H:i:s');
        $claims->save($claim);
        $this->redirect(['action' => 'view', $id]);
        return;
    }
}


===== FILE: src\Controller\Api\ComputeController.php =====

<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\EligibilityService;
use App\Service\ExemptionsRepository;
use App\Service\NationalOverridesRepository;

class ComputeController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    public function compensation(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();
        $journey = (array)($payload['journey'] ?? []);

        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];

        $schedArr = (string)($last['schedArr'] ?? '');
        $actArr = (string)($last['actArr'] ?? '');
        $minutes = null;
        if ($schedArr !== '' && $actArr !== '') {
            $t1 = strtotime($schedArr);
            $t2 = strtotime($actArr);
            if ($t1 && $t2) {
                $minutes = max(0, (int)round(($t2 - $t1) / 60));
            }
        }

        // Fallback to journey.actualArrTime + dep date when segment info missing
        if ($minutes === null) {
            $depDate = (string)($journey['depDate']['value'] ?? '');
            $sched = (string)($journey['schedArrTime']['value'] ?? '');
            $act = (string)($journey['actualArrTime']['value'] ?? '');
            if ($depDate && $sched && $act) {
                $t1 = strtotime($depDate . 'T' . $sched . ':00');
                $t2 = strtotime(($journey['actualArrDate']['value'] ?? $depDate) . 'T' . $act . ':00');
                if ($t1 && $t2) {
                    $minutes = max(0, (int)round(($t2 - $t1) / 60));
                }
            }
        }

        $minutes = $minutes ?? 0;

        // Parse price and currency from simple "99.99 EUR" style value
        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0');
        $price = 0.0;
        $currency = 'EUR';
        if (preg_match('/([0-9]+(?:\.[0-9]{1,2})?)/', $priceRaw, $m)) {
            $price = (float)$m[1];
        }
        if (preg_match('/([A-Z]{3})/i', $priceRaw, $m)) {
            $currency = strtoupper($m[1]);
        }

    // Map JourneyRecord -> eligibility inputs
        $operator = (string)($journey['operatorName']['value'] ?? ($last['operator'] ?? ''));
        $product = (string)($journey['trainCategory']['value'] ?? ($last['trainCategory'] ?? ''));
    $country = (string)($journey['country']['value'] ?? ($payload['country'] ?? ''));

        $svc = new EligibilityService(new ExemptionsRepository(), new NationalOverridesRepository());
        $res = $svc->computeCompensation([
            'delayMin' => $minutes,
            'euOnly' => (bool)($payload['euOnly'] ?? true),
            'refundAlready' => (bool)($payload['refundAlready'] ?? false),
            'knownDelayBeforePurchase' => (bool)($payload['knownDelayBeforePurchase'] ?? false),
            'extraordinary' => (bool)($payload['extraordinary'] ?? false),
            'selfInflicted' => (bool)($payload['selfInflicted'] ?? false),
            'throughTicket' => (bool)($payload['throughTicket'] ?? true),
            'operator' => $operator ?: null,
            'product' => $product ?: null,
            'country' => $country ?: null,
        ]);

        $pct = ((int)($res['percent'] ?? 0)) / 100;
        $amount = round($price * $pct, 2);
        $minPayout = isset($payload['minPayout']) ? (float)$payload['minPayout'] : 0.0;
        $source = $res['source'] ?? 'eu';
        $notes = $res['notes'] ?? null;
        if ($minPayout > 0 && $amount > 0 && $amount < $minPayout) {
            $amount = 0.0;
            $source = 'denied';
            $notes = trim(((string)$notes) . ' Min payout threshold');
        }

        $out = [
            'minutes' => $minutes,
            'pct' => $pct,
            'amount' => $amount,
            'currency' => $currency,
            'source' => $source,
            'notes' => $notes,
        ];
        $this->set($out);
        $this->viewBuilder()->setOption('serialize', array_keys($out));
    }

    public function art12(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();
        $journey = (array)($payload['journey'] ?? []);
        $meta = (array)($payload['meta'] ?? []);
        $svc = new \App\Service\Art12Evaluator();
        $res = $svc->evaluate($journey, $meta);
        $this->set($res);
        $this->viewBuilder()->setOption('serialize', array_keys($res));
    }

    public function exemptions(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();
        $journey = (array)($payload['journey'] ?? []);

        $builder = new \App\Service\ExemptionProfileBuilder();
        $profile = $builder->build($journey);

        $this->set(['profile' => $profile]);
        $this->viewBuilder()->setOption('serialize', ['profile']);
    }

    public function art9(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();
        $journey = (array)($payload['journey'] ?? []);
        $meta = (array)($payload['meta'] ?? []);
        $svc = new \App\Service\Art9Evaluator();
        $res = $svc->evaluate($journey, $meta);
        $this->set($res);
        $this->viewBuilder()->setOption('serialize', array_keys($res));
    }

    public function refund(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();
        $journey = (array)($payload['journey'] ?? []);
        $meta = (array)($payload['meta'] ?? []);
        $svc = new \App\Service\RefundEvaluator();
        $res = $svc->evaluate($journey, $meta);
        $this->set($res);
        $this->viewBuilder()->setOption('serialize', array_keys($res));
    }

    public function refusion(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();
        $journey = (array)($payload['journey'] ?? []);
        $meta = (array)($payload['meta'] ?? []);
        $svc = new \App\Service\Art18RefusionEvaluator();
        $res = $svc->evaluate($journey, $meta);
        $this->set($res);
        $this->viewBuilder()->setOption('serialize', array_keys($res));
    }

    public function claim(): void
    {
        $this->request->allowMethod(['post']);
        $input = (array)$this->request->getData();
        $calc = new \App\Service\ClaimCalculator();
        $out = $calc->calculate($input);
        $this->set($out);
        $this->viewBuilder()->setOption('serialize', array_keys($out));
    }
}


===== FILE: src\Controller\Api\DemoController.php =====

<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class DemoController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * Scan generated mock tickets under mocks/tests/fixtures and run full analysis on each.
     * Optional query: baseDir to override directory.
     */
    public function mockTickets(): void
    {
        $baseDir = (string)($this->request->getQuery('baseDir') ?? (ROOT . DIRECTORY_SEPARATOR . 'mocks' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures'));
        $withRne = (string)($this->request->getQuery('withRne') ?? '0') === '1';
        if (!is_dir($baseDir)) {
            $this->set(['error' => 'not_found', 'baseDir' => $baseDir]);
            $this->viewBuilder()->setOption('serialize', ['error','baseDir']);
            return;
        }

        // Group files by basename (without extension)
        $entries = scandir($baseDir) ?: [];
        $groups = [];
        foreach ($entries as $fn) {
            if ($fn === '.' || $fn === '..') { continue; }
            $ext = strtolower((string)pathinfo($fn, PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf','png','txt'], true)) { continue; }
            $base = (string)pathinfo($fn, PATHINFO_FILENAME);
            if (!isset($groups[$base])) { $groups[$base] = ['pdf' => null, 'png' => null, 'txt' => null]; }
            $groups[$base][$ext] = $baseDir . DIRECTORY_SEPARATOR . $fn;
        }

        $results = [];
        foreach ($groups as $base => $media) {
            // Parse TXT if present; otherwise attempt heuristics from filename
            $txt = '';
            if (!empty($media['txt']) && is_file($media['txt'])) {
                $txt = (string)file_get_contents((string)$media['txt']);
            }

            $parsed = $this->parseMockText($base, $txt);
            $journey = (array)($parsed['journey'] ?? []);
            // Accept both snake_case (preferred) and camelCase from parser
            $art12Meta = (array)($parsed['art12_meta'] ?? ($parsed['art12Meta'] ?? []));
            $art9Meta = (array)($parsed['art9_meta'] ?? ($parsed['art9Meta'] ?? []));
            $refusionMeta = (array)($parsed['refusion_meta'] ?? ($parsed['refusionMeta'] ?? []));
            $compute = (array)($parsed['compute'] ?? []);

            // Profile and evaluations
            $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);
            $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, $art12Meta);
            $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $art9Meta);
            $comp = $this->computeCompensationPreview($journey, $compute);
            $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, ['refundAlready' => (bool)($compute['refundAlready'] ?? false)]);
            $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, $refusionMeta);

            $scenario = [
                'journey' => $journey,
                'refusion_meta' => $refusionMeta,
                'compute' => $compute,
            ];
            $claimOut = (new \App\Service\ClaimCalculator())->calculate($this->mapScenarioToClaimInput($scenario));

            $rne = null;
            if ($withRne) {
                // naive extraction for demo: use product+number or PNR as trainId and schedDep date
                $trainId = $this->matchOne($txt, '/^Train:\s*([^\r\n]+)/mi') ?: ($pnr ?? '');
                $dateIso = $this->dateToIso($this->matchOne($txt, '/^Date:\s*([0-9]{2}\/[0-9]{2}\/[0-9]{4})/mi'));
                if ($trainId && $dateIso) {
                    $rne = (new \App\Service\RneClient())->realtime($trainId, substr($dateIso, 0, 10));
                } else {
                    $rne = [];
                }
            }

            $results[] = [
                'id' => $base,
                'media' => [
                    'pdf' => $media['pdf'],
                    'png' => $media['png'],
                    'txt' => $media['txt'],
                ],
                'rne' => $rne,
                'profile' => $profile,
                'art12' => $art12,
                'art9' => $art9,
                'compensation' => $comp,
                'refund' => $refund,
                'refusion' => $refusion,
                'claim' => $claimOut,
            ];
        }

        $this->set(['results' => $results, 'count' => count($results), 'baseDir' => $baseDir]);
        $this->viewBuilder()->setOption('serialize', ['results','count','baseDir']);
    }

    /** Convert mock TXT content + filename into journey + meta */
    private function parseMockText(string $base, string $txt): array
    {
        $lines = preg_split('/\r?\n/', $txt) ?: [];
        $blob = strtoupper($txt);
    $op = '';
    $product = '';
    $country = '';
    if (str_contains($blob, 'SNCF') || str_contains(strtoupper($base), 'SNCF')) { $op = 'SNCF'; $product = 'TGV'; $country = 'FR'; }
    if (preg_match('/\bDB\b/', $blob) || str_contains(strtoupper($base), 'DB')) { $op = 'DB'; $product = 'ICE'; $country = 'DE'; }
    if (str_contains($blob, 'DSB') || str_contains(strtoupper($base), 'DSB')) { $op = 'DSB'; $product = 'RE'; $country = 'DK'; }
    if (str_contains($blob, 'SJ') || str_contains(strtoupper($base), 'SE_')) { $op = 'SJ'; $product = 'REG'; $country = 'SE'; }
    if (str_contains($blob, 'ZSSK') || str_contains(strtoupper($base), 'SK_')) { $op = 'ZSSK'; $product = 'R'; $country = 'SK'; }
    if (str_contains($blob, 'PKP') || str_contains($blob, 'PKP INTERCITY') || str_contains(strtoupper($base), 'PL_')) { $op = 'PKP'; $product = 'IC'; $country = 'PL'; }

        $pnr = $this->matchOne($txt, '/PNR:\s*([A-Z0-9\-]+)/i');
        $trainRaw = $this->matchOne($txt, '/Train:\s*([^\r\n]+)/i');
        if ($trainRaw) {
            // Split product and number if possible
            if (preg_match('/^([A-ZÅÆØÄÖÜ]+)\s*([0-9]+)/i', $trainRaw, $m)) {
                $product = $product ?: strtoupper($m[1]);
            }
        }

        $from = $this->matchOne($txt, '/^From:\s*(.+)$/mi') ?: $this->matchOne($txt, '/^Fra:\s*(.+)$/mi');
        $to = $this->matchOne($txt, '/^To:\s*(.+)$/mi') ?: $this->matchOne($txt, '/^Til:\s*(.+)$/mi');

    $dateStr = $this->matchOne($txt, '/^Date:\s*([0-9]{2}\/[0-9]{2}\/[0-9]{4})(?:\s+([0-9]{2}:[0-9]{2}))?/mi');
    $schedArrTxt = $this->matchOne($txt, '/^Scheduled Arr:\s*([0-9]{2}:[0-9]{2})/mi');
        $depTime = '';
        $depDate = '';
        if ($dateStr) {
            if (preg_match('/^([0-9]{2}\/[0-9]{2}\/[0-9]{4})(?:\s+([0-9]{2}:[0-9]{2}))?$/', $dateStr, $m)) {
                $depDate = $m[1];
                $depTime = $m[2] ?? '';
            }
        }

        // Special SNCF line with both stations and times
        $sncfLine = null;
        $arrTime = '';
        foreach ($lines as $l) {
            if (str_contains($l, '→') && preg_match('/\((\d{2}:\d{2})\).*→.*\((\d{2}:\d{2})\)/', $l, $mm)) {
                $sncfLine = $l; $depTime = $mm[1]; $arrTime = $mm[2];
                if (!$from && preg_match('/^(.*)\s*\(/', $l, $m1)) { $from = trim($m1[1]); }
                if (!$to && preg_match('/→\s*(.*)\s*\(/', $l, $m2)) { $to = trim($m2[1]); }
                break;
            }
        }

        $schedDep = '';
        $schedArr = '';
        if ($depDate && ($depTime || $arrTime || $schedArrTxt)) {
            $isoDate = $this->dateToIso($depDate);
            if ($depTime) { $schedDep = $isoDate . 'T' . $depTime . ':00'; }
            if ($arrTime) { $schedArr = $isoDate . 'T' . $arrTime . ':00'; }
            if (!$schedArr && $schedArrTxt) { $schedArr = $isoDate . 'T' . $schedArrTxt . ':00'; }
        }

        // If arrival missing, assume +75 minutes to exercise logic
        if (!$schedArr && $schedDep) {
            $schedArr = $this->addMinutes($schedDep, 120); // assume 2h journey
        }

        // Tailor actual arrival to match characteristic delays per known mock
        $actArr = '';
        if ($schedArr) {
            $delta = 75; // default
            $b = strtolower($base);
            if (str_starts_with($b, 'se_regional_lt150')) { $delta = 37; }
            if (str_starts_with($b, 'sk_long_domestic_exempt')) { $delta = 110; }
            if (str_starts_with($b, 'pl_intl_beyond_eu_partial')) { $delta = 0; /* cancellation: no actuals */ }
            $actArr = $delta > 0 ? $this->addMinutes($schedArr, $delta) : '';
        }

        $priceRaw = $this->matchOne($txt, '/^Price:\s*([0-9]+(?:\.[0-9]{1,2})?)\s*([A-Z]{3})/mi');
        $ticketPrice = $priceRaw ? ($priceRaw) : '0 EUR';

        // Scope flags
        $scope = strtolower($this->matchOne($txt, '/^Scope:\s*(.+)$/mi'));
        if ($scope === '') {
            $b = strtolower($base);
            if (str_contains($b, 'intl_beyond_eu') || str_contains($b, 'beyond_eu')) { $scope = 'intl_beyond_eu'; }
            elseif (str_contains($b, 'intl_inside_eu')) { $scope = 'intl_inside_eu'; }
            elseif (str_contains($b, 'long_domestic')) { $scope = 'long_domestic'; }
            elseif (str_contains($b, 'regional')) { $scope = 'regional'; }
        }
        $isLongDomestic = $scope === 'long_domestic';
        $isIntlBeyond = $scope === 'intl_beyond_eu';
        $isIntlInside = $scope === 'intl_inside_eu';

        // Art. 12 meta from disclosure/contract lines if present
        $throughDisclosure = $this->matchOne($txt, '/^Through ticket disclosure:\s*(.+)$/mi') ?: 'unknown';
        $contractType = $this->matchOne($txt, '/^Contract type:\s*(.+)$/mi');

        $journey = [
            'segments' => [[
                'operator' => $op,
                'trainCategory' => $product,
                'country' => $country,
                'pnr' => $pnr,
                'from' => $from,
                'to' => $to,
                'schedDep' => $schedDep,
                'schedArr' => $schedArr,
                'actArr' => $actArr,
            ]],
            'ticketPrice' => ['value' => $ticketPrice],
            'operatorName' => ['value' => $op],
            'trainCategory' => ['value' => $product],
            'country' => ['value' => $country],
            'is_long_domestic' => $isLongDomestic,
            'is_international_beyond_eu' => $isIntlBeyond,
            'is_international_inside_eu' => $isIntlInside,
        ];

        $art12Meta = [
            'through_ticket_disclosure' => $throughDisclosure,
            'contract_type' => $contractType ?: null,
        ];
        $art9Meta = [
            'info_on_rights' => 'Delvist',
        ];
        $refusionMeta = [
            'reason_delay' => true,
            'claim_rerouting' => true,
            'reroute_info_within_100min' => 'Ved ikke',
        ];
        if (str_starts_with(strtolower($base), 'pl_intl_beyond_eu_partial')) {
            $refusionMeta = [
                'reason_cancellation' => true,
                'claim_refund_ticket' => true,
                'claim_rerouting' => false,
                'reroute_info_within_100min' => 'Nej',
            ];
        }
        $compute = [
            'euOnly' => !$isIntlBeyond, // outside-EU parts not in EU scope
            'minPayout' => 4.0,
        ];

        return compact('journey','art12Meta','art9Meta','refusionMeta','compute');
    }

    private function matchOne(string $txt, string $pattern): string
    {
        if (preg_match($pattern, $txt, $m)) { return trim((string)($m[1] ?? '')); }
        return '';
    }

    private function dateToIso(string $dmy): string
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dmy, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return $dmy;
    }

    private function addMinutes(string $iso, int $min): string
    {
        $t = strtotime($iso);
        if ($t) { return date('Y-m-d\TH:i:s', $t + ($min * 60)); }
        return $iso;
    }

    public function fixtures(): void
    {
        $case = (string)($this->request->getQuery('case') ?? 'ice_125m');
        $path = CONFIG . 'demo' . DIRECTORY_SEPARATOR . $case . '.json';
        if (!is_file($path)) {
            $this->set(['error' => 'unknown_case', 'case' => $case]);
            $this->viewBuilder()->setOption('serialize', ['error','case']);
            return;
        }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true) ?: [];
        $this->set($data);
        $this->viewBuilder()->setOption('serialize', array_keys($data));
    }

    public function exemptionFixtures(): void
    {
        $case = (string)($this->request->getQuery('case') ?? 'journey_exemptions_fr_regional');
        $path = CONFIG . 'demo' . DIRECTORY_SEPARATOR . $case . '.json';
        if (!is_file($path)) {
            $this->set(['error' => 'unknown_case', 'case' => $case]);
            $this->viewBuilder()->setOption('serialize', ['error','case']);
            return;
        }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true) ?: [];
        $this->set($data);
        $this->viewBuilder()->setOption('serialize', array_keys($data));
    }

    public function art12Fixtures(): void
    {
        $case = (string)($this->request->getQuery('case') ?? 'journey_art12_through_ticket');
        $path = CONFIG . 'demo' . DIRECTORY_SEPARATOR . $case . '.json';
        if (!is_file($path)) {
            $this->set(['error' => 'unknown_case', 'case' => $case]);
            $this->viewBuilder()->setOption('serialize', ['error','case']);
            return;
        }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true) ?: [];
        $this->set($data);
        $this->viewBuilder()->setOption('serialize', array_keys($data));
    }

    /**
     * Returns a bundle of varied demo scenarios to exercise PDFs/PNGs, exemptions, Art. 12, Art. 9 and compensation fallbacks.
     */
    public function scenarios(): void
    {
        $seed = (string)($this->request->getQuery('seed') ?? 'demo');
        $count = (int)($this->request->getQuery('count') ?? 0);
        $mix = $this->buildScenarios($seed);

        if ($count > 0) {
            $mix = array_slice($mix, 0, $count);
        }

        // Optionally shuffle for variety
        if ($seed !== 'fixed') {
            shuffle($mix);
        }
        $out = ['scenarios' => $mix];
        $this->set($out);
        $this->viewBuilder()->setOption('serialize', ['scenarios']);
    }

    /**
     * POST: Runs exemption profile, Art.12, Art.9 and compensation over the generated scenarios.
     * Accepts optional body: { seed?: string, count?: int, scenarios?: array }
     */
    public function runScenarios(): void
    {
        $method = strtoupper((string)$this->request->getMethod());
        if ($method === 'GET') {
            // Support GET for convenience in browser: use query params
            $seed = (string)($this->request->getQuery('seed') ?? 'demo');
            $count = (int)($this->request->getQuery('count') ?? 0);
            $scenarios = $this->buildScenarios($seed);
        } else {
            $this->request->allowMethod(['post']);
            $payload = (array)$this->request->getData();
            $seed = (string)($payload['seed'] ?? 'demo');
            $count = (int)($payload['count'] ?? 0);
            $scenarios = (array)($payload['scenarios'] ?? $this->buildScenarios($seed));
        }
        if ($count > 0) {
            $scenarios = array_slice($scenarios, 0, $count);
        }

        $results = [];
        foreach ($scenarios as $scenario) {
            $journey = (array)($scenario['journey'] ?? []);
            $art12Meta = (array)($scenario['art12_meta'] ?? []);
            $art9Meta = (array)($scenario['art9_meta'] ?? []);
            $compute = (array)($scenario['compute'] ?? []);
            $refundMeta = [
                'refundAlready' => (bool)($compute['refundAlready'] ?? false),
            ];
            $refusionMeta = (array)($scenario['refusion_meta'] ?? []);

            // Exemptions
            $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);

            // Art. 12 and Art. 9
            $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, $art12Meta);
            $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $art9Meta);

            // Compensation
            $comp = $this->computeCompensationPreview($journey, $compute);

            // Refund (Art. 18-like)
            $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, $refundMeta);

            // Step Refusion (Art. 18 + CIV + 10 + dele af 20)
            $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, $refusionMeta);

            // Unified claim sample
            $claimInput = $this->mapScenarioToClaimInput($scenario);
            $claimOut = (new \App\Service\ClaimCalculator())->calculate($claimInput);

            $results[] = [
                'id' => $scenario['id'] ?? null,
                'media' => $scenario['media'] ?? null,
                'profile' => $profile,
                'art12' => $art12,
                'art9' => $art9,
                'compensation' => $comp,
                'refund' => $refund,
                'refusion' => $refusion,
                'claim' => $claimOut,
            ];
        }

        $this->set(['results' => $results]);
        $this->viewBuilder()->setOption('serialize', ['results']);
    }

    /** Map scenario into ClaimInput (simplified) */
    private function mapScenarioToClaimInput(array $scenario): array
    {
        $journey = (array)($scenario['journey'] ?? []);
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];
        $country = (string)($journey['country']['value'] ?? ($last['country'] ?? ''));
        $currency = 'EUR';
        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0 EUR');
        if (preg_match('/([A-Z]{3})/i', $priceRaw, $m)) { $currency = strtoupper($m[1]); }
        $price = 0.0;
        if (preg_match('/([0-9]+(?:\.[0-9]{1,2})?)/', $priceRaw, $m)) { $price = (float)$m[1]; }

        $legs = [];
        foreach ($segments as $s) {
            $legs[] = [
                'from' => $s['from'] ?? '',
                'to' => $s['to'] ?? '',
                'eu' => true, // assume EU for demo; real pipeline should mark per segment
                'scheduled_dep' => $s['schedDep'] ?? '',
                'scheduled_arr' => $s['schedArr'] ?? '',
                'actual_dep' => $s['actDep'] ?? null,
                'actual_arr' => $s['actArr'] ?? null,
            ];
        }

        $delayMin = 0;
        $schedArr = (string)($last['schedArr'] ?? '');
        $actArr = (string)($last['actArr'] ?? '');
        if ($schedArr && $actArr) {
            $t1 = strtotime($schedArr); $t2 = strtotime($actArr);
            if ($t1 && $t2) { $delayMin = max(0, (int)round(($t2 - $t1)/60)); }
        }

        $extraordinary = (bool)($scenario['compute']['extraordinary'] ?? false);
        $selfInflicted = (bool)($scenario['compute']['selfInflicted'] ?? false);
        $notified = (bool)($scenario['compute']['knownDelayBeforePurchase'] ?? false);

        return [
            'country_code' => $country ?: 'EU',
            'currency' => $currency,
            'ticket_price_total' => $price,
            'trip' => [
                'through_ticket' => true,
                'legs' => $legs,
            ],
            'disruption' => [
                'delay_minutes_final' => $delayMin,
                'notified_before_purchase' => $notified,
                'extraordinary' => $extraordinary,
                'self_inflicted' => $selfInflicted,
            ],
            'choices' => [
                'wants_refund' => (bool)($scenario['refusion_meta']['claim_refund_ticket'] ?? false),
                'wants_reroute_same_soonest' => ($scenario['refusion_meta']['reroute_same_conditions_soonest'] ?? 'Ved ikke') === 'Ja',
                'wants_reroute_later_choice' => ($scenario['refusion_meta']['reroute_later_at_choice'] ?? 'Ved ikke') === 'Ja',
            ],
            'expenses' => [
                // A minimal mapping; real form would pass numeric amounts
                'meals' => 0,
                'hotel' => 0,
                'alt_transport' => 0,
                'other' => 0,
            ],
            'already_refunded' => 0,
        ];
    }

    /**
     * Build the base scenarios list. Caller may shuffle/slice.
     * @param string $seed
     * @return array<int,array<string,mixed>>
     */
    private function buildScenarios(string $seed): array
    {
        $mix = [
            [
                'id' => 'sncf_png_through_ticket_ok',
                'media' => ['png' => 'tests/fixtures/sncf_ticket_through.png'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'SNCF', 'trainCategory' => 'TGV', 'country' => 'FR', 'pnr' => 'ABC123', 'schedArr' => '2025-01-02T10:00:00', 'actArr' => '2025-01-02T11:15:00'],
                    ],
                    'ticketPrice' => ['value' => '120.00 EUR'],
                    'operatorName' => ['value' => 'SNCF'],
                    'trainCategory' => ['value' => 'TGV'],
                    'country' => ['value' => 'FR'],
                    'is_international_inside_eu' => true,
                ],
                'art12_meta' => [
                    'through_ticket_disclosure' => 'Gennemgående',
                    'single_txn_operator' => 'Ja',
                    'separate_contract_notice' => 'unknown',
                ],
                'art9_meta' => [
                    'info_before_purchase' => 'Ja',
                    'info_on_rights' => 'Delvist',
                    'info_during_disruption' => 'unknown',
                    'language_accessible' => 'Ja',
                    'accessibility_format' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_delay' => true,
                    'claim_refund_ticket' => false,
                    'claim_rerouting' => true,
                    'reroute_info_within_100min' => 'Ja',
                    'meal_offered' => 'Ja',
                ],
                'compute' => [
                    'euOnly' => true,
                    'minPayout' => 4.0,
                ],
            ],
            [
                'id' => 'denial_paths_extraordinary_refund',
                'media' => ['png' => 'tests/fixtures/denial_sample.png'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'DSB', 'trainCategory' => 'REG', 'country' => 'DK', 'pnr' => 'DK9', 'schedArr' => '2025-05-01T08:00:00', 'actArr' => '2025-05-01T08:40:00'],
                    ],
                    'ticketPrice' => ['value' => '8.00 EUR'],
                    'operatorName' => ['value' => 'DSB'],
                    'trainCategory' => ['value' => 'REG'],
                    'country' => ['value' => 'DK'],
                ],
                'art12_meta' => [
                    'through_ticket_disclosure' => 'unknown',
                ],
                'art9_meta' => [
                    'info_on_rights' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_delay' => true,
                    'claim_refund_ticket' => true,
                    'refund_requested' => 'Ja',
                ],
                'compute' => [
                    'euOnly' => true,
                    'extraordinary' => true,
                    'refundAlready' => true,
                    'minPayout' => 4.0,
                ],
            ],
            [
                'id' => 'db_pdf_separate_contracts_agency',
                'media' => ['pdf' => 'tests/fixtures/db_ticket_separate.pdf'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'DB', 'trainCategory' => 'ICE', 'country' => 'DE', 'pnr' => 'X1', 'schedArr' => '2025-03-05T12:00:00', 'actArr' => '2025-03-05T14:30:00'],
                        ['operator' => 'CD', 'trainCategory' => 'EC', 'country' => 'CZ', 'pnr' => 'Y2', 'schedArr' => '2025-03-05T16:00:00', 'actArr' => '2025-03-05T17:45:00'],
                    ],
                    'ticketPrice' => ['value' => '89.00 EUR'],
                    'operatorName' => ['value' => 'DB'],
                    'trainCategory' => ['value' => 'ICE'],
                    'country' => ['value' => 'DE'],
                    'seller_type' => 'agency',
                    'is_international_inside_eu' => true,
                ],
                'art12_meta' => [
                    'through_ticket_disclosure' => 'Særskilte',
                    'separate_contract_notice' => 'Nej',
                ],
                'art9_meta' => [
                    'info_before_purchase' => 'Delvist',
                    'info_on_rights' => 'Nej',
                    'info_during_disruption' => 'Nej',
                    'language_accessible' => 'unknown',
                    'accessibility_format' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_missed_conn' => true,
                    'claim_rerouting' => true,
                    'reroute_info_within_100min' => 'Nej',
                    'meal_offered' => 'Nej',
                    'alt_transport_provided' => 'Nej',
                ],
                'compute' => [
                    'euOnly' => true,
                    'extraordinary' => false,
                    'minPayout' => 4.0,
                ],
            ],
            [
                'id' => 'long_domestic_sk_exemptions',
                'media' => ['png' => 'tests/fixtures/sk_ticket.png'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'ZSSK', 'trainCategory' => 'R', 'country' => 'SK', 'pnr' => 'ZZ9', 'schedArr' => '2025-02-10T09:00:00', 'actArr' => '2025-02-10T10:05:00'],
                    ],
                    'ticketPrice' => ['value' => '12.00 EUR'],
                    'operatorName' => ['value' => 'ZSSK'],
                    'trainCategory' => ['value' => 'R'],
                    'country' => ['value' => 'SK'],
                    'is_long_domestic' => true,
                ],
                'art12_meta' => [],
                'art9_meta' => [
                    'info_before_purchase' => 'unknown',
                    'info_on_rights' => 'unknown',
                    'info_during_disruption' => 'unknown',
                    'language_accessible' => 'unknown',
                    'accessibility_format' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_delay' => true,
                    'claim_refund_ticket' => false,
                    'claim_rerouting' => true,
                    'reroute_info_within_100min' => 'Ved ikke',
                    'meal_offered' => 'Ved ikke',
                ],
                'compute' => [
                    'euOnly' => true,
                    'minPayout' => 4.0,
                ],
            ],
            [
                'id' => 'intl_beyond_eu_partial',
                'media' => ['pdf' => 'tests/fixtures/int_beyond_eu.pdf'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'PKP', 'trainCategory' => 'IC', 'country' => 'PL', 'pnr' => 'PL1', 'schedArr' => '2025-04-01T18:00:00', 'actArr' => '2025-04-01T19:20:00'],
                        ['operator' => 'BY', 'trainCategory' => 'INT', 'country' => 'BY', 'pnr' => 'BY2', 'schedArr' => '2025-04-01T22:00:00', 'actArr' => '2025-04-01T22:00:00'],
                    ],
                    'ticketPrice' => ['value' => '60.00 EUR'],
                    'operatorName' => ['value' => 'PKP'],
                    'trainCategory' => ['value' => 'IC'],
                    'country' => ['value' => 'PL'],
                    'is_international_beyond_eu' => true,
                ],
                'art12_meta' => [
                    'through_ticket_disclosure' => 'unknown',
                ],
                'art9_meta' => [
                    'info_before_purchase' => 'unknown',
                    'info_on_rights' => 'Delvist',
                    'info_during_disruption' => 'Ja',
                    'language_accessible' => 'Delvist',
                    'accessibility_format' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_cancellation' => true,
                    'claim_refund_ticket' => true,
                    'refund_requested' => 'Nej',
                    'meal_offered' => 'Ja',
                ],
                'compute' => [
                    'euOnly' => false,
                    'minPayout' => 0.0,
                ],
            ],
        ];
        return $mix;
    }

    /**
     * Compute a compensation preview mirroring ComputeController::compensation logic.
     * @param array $journey
     * @param array $payload
     * @return array{minutes:int,pct:float,amount:float,currency:string,source:string,notes:?string}
     */
    private function computeCompensationPreview(array $journey, array $payload): array
    {
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];

        $schedArr = (string)($last['schedArr'] ?? '');
        $actArr = (string)($last['actArr'] ?? '');
        $minutes = null;
        if ($schedArr !== '' && $actArr !== '') {
            $t1 = strtotime($schedArr);
            $t2 = strtotime($actArr);
            if ($t1 && $t2) {
                $minutes = max(0, (int)round(($t2 - $t1) / 60));
            }
        }
        if ($minutes === null) {
            $depDate = (string)($journey['depDate']['value'] ?? '');
            $sched = (string)($journey['schedArrTime']['value'] ?? '');
            $act = (string)($journey['actualArrTime']['value'] ?? '');
            if ($depDate && $sched && $act) {
                $t1 = strtotime($depDate . 'T' . $sched . ':00');
                $t2 = strtotime(($journey['actualArrDate']['value'] ?? $depDate) . 'T' . $act . ':00');
                if ($t1 && $t2) {
                    $minutes = max(0, (int)round(($t2 - $t1) / 60));
                }
            }
        }
        $minutes = $minutes ?? 0;

        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0');
        $price = 0.0;
        $currency = 'EUR';
        if (preg_match('/([0-9]+(?:\.[0-9]{1,2})?)/', $priceRaw, $m)) {
            $price = (float)$m[1];
        }
        if (preg_match('/([A-Z]{3})/i', $priceRaw, $m)) {
            $currency = strtoupper($m[1]);
        }

        $operator = (string)($journey['operatorName']['value'] ?? ($last['operator'] ?? ''));
        $product = (string)($journey['trainCategory']['value'] ?? ($last['trainCategory'] ?? ''));
        $country = (string)($journey['country']['value'] ?? ($payload['country'] ?? ''));

        $svc = new \App\Service\EligibilityService(new \App\Service\ExemptionsRepository(), new \App\Service\NationalOverridesRepository());
        $res = $svc->computeCompensation([
            'delayMin' => $minutes,
            'euOnly' => (bool)($payload['euOnly'] ?? true),
            'refundAlready' => (bool)($payload['refundAlready'] ?? false),
            'knownDelayBeforePurchase' => (bool)($payload['knownDelayBeforePurchase'] ?? false),
            'extraordinary' => (bool)($payload['extraordinary'] ?? false),
            'selfInflicted' => (bool)($payload['selfInflicted'] ?? false),
            'throughTicket' => (bool)($payload['throughTicket'] ?? true),
            'operator' => $operator ?: null,
            'product' => $product ?: null,
            'country' => $country ?: null,
        ]);

        $pct = ((int)($res['percent'] ?? 0)) / 100;
        $amount = round($price * $pct, 2);
        $minPayout = isset($payload['minPayout']) ? (float)$payload['minPayout'] : 0.0;
        $source = $res['source'] ?? 'eu';
        $notes = $res['notes'] ?? null;
        if ($minPayout > 0 && $amount > 0 && $amount < $minPayout) {
            $amount = 0.0;
            $source = 'denied';
            $notes = trim(((string)$notes) . ' Min payout threshold');
        }

        return [
            'minutes' => $minutes,
            'pct' => $pct,
            'amount' => $amount,
            'currency' => $currency,
            'source' => $source,
            'notes' => $notes,
        ];
}
}


===== FILE: src\Controller\Api\IngestController.php =====

<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class IngestController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    public function ticket(): void
    {
        $this->request->allowMethod(['post']);
        $this->set(['journey' => ['segments' => [], 'sourceHashes' => []], 'logs' => ['stub']]);
        $this->viewBuilder()->setOption('serialize', ['journey','logs']);
    }
}


===== FILE: src\Controller\Api\OperatorController.php =====

<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class OperatorController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    public function trip(string $operatorCode): void
    {
        $this->set(['operator' => $operatorCode, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['operator','segments']);
    }
}


===== FILE: src\Controller\Api\ProvidersController.php =====

<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class ProvidersController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    public function sncfBookingValidate(): void
    {
        $this->request->allowMethod(['post']);
        $pnr = (string)($this->request->getData('pnr') ?? '');
        $this->set(['pnr' => $pnr, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['pnr','segments']);
    }

    public function sncfTrains(): void
    {
        $date = (string)($this->request->getQuery('date') ?? '');
        $trainNo = (string)($this->request->getQuery('trainNo') ?? '');
        $this->set(['date' => $date, 'trainNo' => $trainNo, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['date','trainNo','segments']);
    }

    public function sncfRealtime(): void
    {
        $trainUid = (string)($this->request->getQuery('trainUid') ?? '');
        $this->set(['trainUid' => $trainUid, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['trainUid','segments']);
    }

    public function dbLookup(): void
    {
        $pnr = (string)($this->request->getQuery('pnr') ?? '');
        $this->set(['pnr' => $pnr, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['pnr','segments']);
    }

    public function dbTrip(): void
    {
        $trainNo = (string)($this->request->getQuery('trainNo') ?? '');
        $date = (string)($this->request->getQuery('date') ?? '');
        $this->set(['trainNo' => $trainNo, 'date' => $date, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['trainNo','date','segments']);
    }

    public function dbRealtime(): void
    {
        $evaId = (string)($this->request->getQuery('evaId') ?? '');
        $time = (string)($this->request->getQuery('time') ?? '');
        $this->set(['evaId' => $evaId, 'time' => $time, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['evaId','time','segments']);
    }

    public function dsbTrip(): void
    {
        $trainNo = (string)($this->request->getQuery('trainNo') ?? '');
        $date = (string)($this->request->getQuery('date') ?? '');
        $this->set(['trainNo' => $trainNo, 'date' => $date, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['trainNo','date','segments']);
    }

    public function dsbRealtime(): void
    {
        $uic = (string)($this->request->getQuery('uic') ?? '');
        $this->set(['uic' => $uic, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['uic','segments']);
    }

    public function rneRealtime(): void
    {
        $trainId = (string)($this->request->getQuery('trainId') ?? '');
        $date = (string)($this->request->getQuery('date') ?? '');
        $this->set(['trainId' => $trainId, 'date' => $date, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['trainId','date','segments']);
    }

    public function openRealtime(): void
    {
        $country = (string)($this->request->getQuery('country') ?? '');
        $trainNo = (string)($this->request->getQuery('trainNo') ?? '');
        $date = (string)($this->request->getQuery('date') ?? '');
        $this->set(['country' => $country, 'trainNo' => $trainNo, 'date' => $date, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['country','trainNo','date','segments']);
    }
}


===== FILE: src\Controller\Api\RneController.php =====

<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class RneController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    public function trip(): void
    {
        $this->set(['segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['segments']);
    }
}


===== FILE: src\Controller\AppController.php =====

<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/5/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
    }
}


===== FILE: src\Controller\ClaimsController.php =====

<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\EligibilityService;
use App\Service\ExemptionsRepository;
use App\Service\NationalOverridesRepository;

class ClaimsController extends AppController
{
    public function start(): void
    {
        // Simple landing page for the wizard
    }

    public function compute(): void
    {
        $this->request->allowMethod(['post']);

        $delay = (int)($this->request->getData('delay_min') ?? 0);
        $ctx = [
            'delayMin' => $delay,
            'euOnly' => true,
            'refundAlready' => (bool)$this->request->getData('refund_already'),
            'knownDelayBeforePurchase' => (bool)$this->request->getData('known_delay_before_purchase'),
            'extraordinary' => (bool)$this->request->getData('extraordinary'),
            'selfInflicted' => (bool)$this->request->getData('self_inflicted'),
            'country' => (string)($this->request->getData('country') ?? ''),
            'operator' => (string)($this->request->getData('operator') ?? ''),
            'product' => (string)($this->request->getData('product') ?? ''),
        ];
        $service = new EligibilityService(new ExemptionsRepository(), new NationalOverridesRepository());
        $result = $service->computeCompensation($ctx);
        $this->set(compact('result', 'ctx'));
        $this->viewBuilder()->setTemplate('result');
    }
}


===== FILE: src\Controller\ClientClaimsController.php =====

<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\EligibilityService;
use App\Service\ExemptionsRepository;
use App\Service\NationalOverridesRepository;

class ClientClaimsController extends AppController
{
    public function start(): void
    {
        // Client-facing simple form to submit a claim
    }

    public function submit(): void
    {
        $this->request->allowMethod(['post']);
        $data = (array)$this->request->getData();

        $claims = $this->fetchTable('Claims');
        $claim = $claims->newEntity([
            'client_name' => (string)($data['name'] ?? ''),
            'client_email' => (string)($data['email'] ?? ''),
            'country' => (string)($data['country'] ?? ''),
            'operator' => (string)($data['operator'] ?? ''),
            'product' => (string)($data['product'] ?? ''),
            'delay_min' => (int)($data['delay_min'] ?? 0),
            'refund_already' => !empty($data['refund_already']),
            'known_delay_before_purchase' => !empty($data['known_delay_before_purchase']),
            'extraordinary' => !empty($data['extraordinary']),
            'self_inflicted' => !empty($data['self_inflicted']),
            'ticket_price' => (float)($data['ticket_price'] ?? 0),
            'currency' => (string)($data['currency'] ?? 'EUR'),
            'assignment_accepted' => !empty($data['assignment_accepted']),
        ]);

        if (empty($claim->assignment_accepted)) {
            $claim->setError('assignment_accepted', ['required' => 'Du skal acceptere overdragelsen for at fortsætte.']);
        }

        if ($claim->getErrors()) {
            $this->set('errors', $claim->getErrors());
            $this->viewBuilder()->setTemplate('start');
            return;
        }

        $service = new EligibilityService(new ExemptionsRepository(), new NationalOverridesRepository());
        $res = $service->computeCompensation([
            'delayMin' => $claim->delay_min,
            'euOnly' => true,
            'refundAlready' => (bool)$claim->refund_already,
            'knownDelayBeforePurchase' => (bool)$claim->known_delay_before_purchase,
            'extraordinary' => (bool)$claim->extraordinary,
            'selfInflicted' => (bool)$claim->self_inflicted,
            'country' => $claim->country,
            'operator' => $claim->operator,
            'product' => $claim->product,
        ]);

        $claim->computed_percent = (int)($res['percent'] ?? 0);
        $claim->computed_source = (string)($res['source'] ?? 'eu');
        $claim->computed_notes = $res['notes'] ?? null;
        $claim->compensation_amount = round(((float)$claim->ticket_price) * $claim->computed_percent / 100, 2);
        $claim->fee_amount = round(((float)$claim->compensation_amount) * $claim->fee_percent / 100, 2);
        $claim->payout_amount = max(0, (float)$claim->compensation_amount - (float)$claim->fee_amount);

    if ($claims->save($claim)) {
            // Handle uploads (ticket, receipts, delay confirmation)
            $files = $this->request->getUploadedFiles();
            $dir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'cases' . DIRECTORY_SEPARATOR . $claim->case_number;
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $attachments = $this->fetchTable('ClaimAttachments');
            foreach ([
                'ticket_file' => 'ticket',
                'receipts_file' => 'receipts',
                'delay_confirmation_file' => 'delay_confirmation',
            ] as $field => $type) {
                if (!isset($files[$field])) { continue; }
                $file = $files[$field];
                if ($file->getError() === UPLOAD_ERR_OK && $file->getSize() > 0) {
                    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientFilename());
                    $target = $dir . DIRECTORY_SEPARATOR . $type . '_' . $safe;
                    $file->moveTo($target);
                    $attachments->save($attachments->newEntity([
                        'claim_id' => $claim->id,
                        'type' => $type,
                        'path' => 'files/cases/' . $claim->case_number . '/' . basename($target),
                        'original_name' => $file->getClientFilename(),
                        'size' => (int)$file->getSize(),
                    ]));
                }
            }

            // Generate assignment PDF and update record
            try {
                $dir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'cases' . DIRECTORY_SEPARATOR . $claim->case_number;
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $fsPath = $dir . DIRECTORY_SEPARATOR . 'assignment.pdf';
                $webPath = '/files/cases/' . rawurlencode($claim->case_number) . '/assignment.pdf';

                $pdf = new \FPDF('P', 'mm', 'A4');
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 16);
                $pdf->Cell(0, 10, 'Assignment of Claim', 0, 1);
                $pdf->SetFont('Arial', '', 12);
                $pdf->MultiCell(0, 7, 'Case: ' . $claim->case_number);
                $pdf->MultiCell(0, 7, 'Client: ' . $claim->client_name . ' <' . $claim->client_email . '>');
                $pdf->MultiCell(0, 7, 'Operator/Product: ' . ($claim->operator ?? '') . ' / ' . ($claim->product ?? ''));
                $pdf->Ln(4);
                $pdf->MultiCell(0, 7, 'The client hereby assigns all rights to pursue reimbursement/compensation for the journey to the legal representative. Immediate payout provided to client, net of agreed fee.');
                $pdf->Ln(4);
                $pdf->MultiCell(0, 7, 'Computed compensation basis: ' . (int)$claim->computed_percent . '% (' . ($claim->computed_source ?? 'eu') . '), amount ' . number_format((float)$claim->compensation_amount, 2) . ' ' . ($claim->currency ?? 'EUR'));
                $pdf->MultiCell(0, 7, 'Fee: ' . (int)$claim->fee_percent . '% = ' . number_format((float)$claim->fee_amount, 2) . ' ' . ($claim->currency ?? 'EUR'));
                $pdf->MultiCell(0, 7, 'Payout to client: ' . number_format((float)$claim->payout_amount, 2) . ' ' . ($claim->currency ?? 'EUR'));
                $pdf->Ln(10);
                $pdf->MultiCell(0, 7, 'Date: ' . date('Y-m-d H:i'));
                $pdf->Output('F', $fsPath);

                $claim->assignment_pdf = 'files/cases/' . $claim->case_number . '/assignment.pdf';
                $claims->save($claim);
            } catch (\Throwable $e) {
                // Non-fatal: continue without assignment file
            }

            // Instant payout simulation
            try {
                $pay = new \App\Service\PaymentsService();
                $resPay = $pay->payout((float)$claim->payout_amount, (string)$claim->currency, [
                    'name' => (string)$claim->client_name,
                    'email' => (string)$claim->client_email,
                ]);
                if ($resPay['status'] === 'paid') {
                    $claim->payout_status = 'paid';
                    $claim->payout_reference = $resPay['reference'];
                    $claim->paid_at = date('Y-m-d H:i:s');
                    $claims->save($claim);
                }
            } catch (\Throwable $e) {
                // keep as pending if any error
            }

            $this->set('claim', $claim);
            $this->viewBuilder()->setTemplate('submitted');
            return;
        }

        $this->set('saveError', true);
        $this->viewBuilder()->setTemplate('start');
    }
}


===== FILE: src\Controller\ClientWizardController.php =====

<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Utility\Text;

class ClientWizardController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('App');
    }

    // Step 1: Upload or paste journey JSON
    public function start(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $state = (array)$session->read('wizard.claim');

        if ($this->request->is('post')) {
            $data = (array)$this->request->getData();
            // Demo: autofill random scenario incl. simulated upload
            if (!empty($data['autofill'])) {
                $scenario = $this->buildDemoScenario();
                $profile = (new \App\Service\ExemptionProfileBuilder())->build($scenario['journey']);
                $art12 = (new \App\Service\Art12Evaluator())->evaluate($scenario['journey'], []);
                $art9 = (new \App\Service\Art9Evaluator())->evaluate($scenario['journey'], []);
                $refund = (new \App\Service\RefundEvaluator())->evaluate($scenario['journey'], ['refundAlready' => false]);
                $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($scenario['journey'], []);
                $state = [
                    'journey' => $scenario['journey'],
                    'uploads' => ['ticket' => $scenario['uploaded']],
                    'profile' => $profile,
                    'art12' => $art12,
                    'art9' => $art9,
                    'refund_eval' => $refund,
                    'refusion_eval' => $refusion,
                    'answers' => $scenario['answers'],
                ];
                $session->write('wizard.claim', $state);
                return $this->redirect(['action' => 'summary']);
            }
            $journey = $this->extractJourneyFromInput($data);
            $savedPath = $this->saveUploadedFile('ticket');

            // Build profile + base evals so we can drive questions
            $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);
            $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, []);
            $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, []);
            $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, ['refundAlready' => false]);
            $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, []);

            $state = [
                'journey' => $journey,
                'uploads' => [ 'ticket' => $savedPath ],
                'profile' => $profile,
                'art12' => $art12,
                'art9' => $art9,
                'refund_eval' => $refund,
                'refusion_eval' => $refusion,
                'answers' => [],
            ];
            $session->write('wizard.claim', $state);
            return $this->redirect(['action' => 'questions']);
        }

        $this->set('state', $state);
        return null;
    }

    // Step 2: Ask fallbacks for missing info (Art.12/9/18/19 inputs)
    public function questions(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $state = (array)$session->read('wizard.claim');
        if (!$state) { return $this->redirect(['action' => 'start']); }

        if ($this->request->is('post')) {
            $a = (array)$this->request->getData();
            $answers = array_merge((array)($state['answers'] ?? []), [
                'country' => (string)($a['country'] ?? ''),
                'service_scope' => (string)($a['service_scope'] ?? ''),
                'through_ticket_disclosure' => (string)($a['through_ticket_disclosure'] ?? 'unknown'),
                'separate_contract_notice' => (string)($a['separate_contract_notice'] ?? 'unknown'),
                'info_before_purchase' => (string)($a['info_before_purchase'] ?? 'unknown'),
                'info_on_rights' => (string)($a['info_on_rights'] ?? 'unknown'),
                'info_during_disruption' => (string)($a['info_during_disruption'] ?? 'unknown'),
                'language_accessible' => (string)($a['language_accessible'] ?? 'unknown'),
                'delay_minutes_final' => (int)($a['delay_minutes_final'] ?? 0),
                'notified_before_purchase' => !empty($a['notified_before_purchase']),
                'extraordinary' => !empty($a['extraordinary']),
                'self_inflicted' => !empty($a['self_inflicted']),
                'wants_refund' => !empty($a['wants_refund']),
                'wants_reroute_same_soonest' => !empty($a['wants_reroute_same_soonest']),
                'wants_reroute_later_choice' => !empty($a['wants_reroute_later_choice']),
            ]);
            $state['answers'] = $answers;
            $session->write('wizard.claim', $state);
            return $this->redirect(['action' => 'expenses']);
        }

        $this->set('state', $state);
        return null;
    }

    // Step 3: Expenses (Art. 20(2))
    public function expenses(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $state = (array)$session->read('wizard.claim');
        if (!$state) { return $this->redirect(['action' => 'start']); }

        if ($this->request->is('post')) {
            $a = (array)$this->request->getData();
            $answers = array_merge((array)($state['answers'] ?? []), [
                'currency' => (string)($a['currency'] ?? 'EUR'),
                'meals' => (float)($a['meals'] ?? 0),
                'hotel' => (float)($a['hotel'] ?? 0),
                'alt_transport' => (float)($a['alt_transport'] ?? 0),
                'other' => (float)($a['other'] ?? 0),
            ]);
            $state['answers'] = $answers;
            $session->write('wizard.claim', $state);
            return $this->redirect(['action' => 'summary']);
        }

        $this->set('state', $state);
        return null;
    }

    // Step 4: Summary and confirmation
    public function summary(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $state = (array)$session->read('wizard.claim');
        if (!$state) { return $this->redirect(['action' => 'start']); }

        $calc = $this->computeClaimFromState($state);
        $this->set('calc', $calc);
        $this->set('state', $state);

        if ($this->request->is('post')) {
            $data = (array)$this->request->getData();
            if (empty($data['assignment_accepted'])) {
                $this->set('error', 'Du skal acceptere overdragelsen for at fortsætte.');
                    return null;
            }
            // Persist a minimal claim using existing Claims table if present
            try {
                $claims = $this->fetchTable('Claims');
                $claim = $claims->newEntity([
                    'client_name' => (string)($data['name'] ?? ''),
                    'client_email' => (string)($data['email'] ?? ''),
                    'country' => (string)($state['journey']['country']['value'] ?? ''),
                    'operator' => (string)($state['journey']['operatorName']['value'] ?? ''),
                    'product' => (string)($state['journey']['trainCategory']['value'] ?? ''),
                    'delay_min' => (int)($state['answers']['delay_minutes_final'] ?? 0),
                    'refund_already' => false,
                    'known_delay_before_purchase' => (bool)($state['answers']['notified_before_purchase'] ?? false),
                    'extraordinary' => (bool)($state['answers']['extraordinary'] ?? false),
                    'self_inflicted' => (bool)($state['answers']['self_inflicted'] ?? false),
                    'ticket_price' => (float)($state['journey']['ticketPrice']['value'] ?? 0),
                    'currency' => (string)($state['answers']['currency'] ?? 'EUR'),
                    'assignment_accepted' => true,
                    'computed_percent' => (int)($calc['breakdown']['compensation']['pct'] ?? 0),
                    'computed_source' => (string)($calc['breakdown']['compensation']['source'] ?? 'eu'),
                    'computed_notes' => (string)($calc['breakdown']['compensation']['notes'] ?? ''),
                    'compensation_amount' => (float)($calc['breakdown']['compensation']['amount'] ?? 0),
                    'fee_percent' => 25,
                    'fee_amount' => (float)($calc['totals']['service_fee_amount'] ?? 0),
                    'payout_amount' => (float)($calc['totals']['net_to_client'] ?? 0),
                ]);
                $claims->save($claim);
            } catch (\Throwable $e) {
                // Ignore persistence issues in MVP
            }

            // Clear session and show submitted
            $session->delete('wizard.claim');
            $this->set('calc', $calc);
            $this->viewBuilder()->setTemplate('submitted');
                return null;
        }
        return null;
    }

    private function extractJourneyFromInput(array $data): array
    {
        $journey = [];
        $json = (string)($data['journey'] ?? '');
        if ($json) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) { $journey = $decoded; }
        }
        if (empty($journey)) {
            $country = (string)($data['country'] ?? 'FR');
            $amountNum = null;
            $currency = null;
            if (isset($data['ticket_amount']) && (string)$data['ticket_amount'] !== '') {
                $amountNum = (float)$data['ticket_amount'];
            }
            if (!empty($data['ticket_currency'])) {
                $currency = strtoupper((string)$data['ticket_currency']);
            }
            $fallbackText = (string)($data['ticket_price'] ?? '');
            if ($fallbackText && $amountNum === null) {
                if (preg_match('/([0-9]+(?:\.[0-9]{1,2})?)/', $fallbackText, $m)) { $amountNum = (float)$m[1]; }
                if (preg_match('/([A-Z]{3})/i', $fallbackText, $m)) { $currency = strtoupper($m[1]); }
            }
            $priceString = ($amountNum !== null) ? number_format($amountNum, 2, '.', '') . ' ' . ($currency ?: 'EUR') : ((string)$data['ticket_price'] ?: '0 EUR');
            $journey = [
                'segments' => [[ 'country' => $country ]],
                'ticketPrice' => ['value' => $priceString],
            ];
        }
        return $journey;
    }

    private function saveUploadedFile(string $field): ?string
    {
        $file = $this->request->getData($field);
        if (!$file || !is_array($file) || (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
            return null;
        }
        $tmp = $file['tmp_name'];
        $name = $file['name'] ?? ('ticket_' . Text::uuid());
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$name) ?: ('ticket_' . Text::uuid());
        $destDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($destDir)) { mkdir($destDir, 0775, true); }
        $dest = $destDir . DIRECTORY_SEPARATOR . $safe;
        if (@move_uploaded_file($tmp, $dest)) {
            return '/files/uploads/' . rawurlencode($safe);
        }
        return null;
    }

    /** Build a random demo scenario and simulate an uploaded ticket by copying or creating a placeholder */
    private function buildDemoScenario(): array
    {
        $kinds = ['eu_comp_ok','se_regional_exempt','sk_long_exempt','pl_beyond_eu_cancel'];
        $kind = $kinds[array_rand($kinds)];
        $journey = [];
        $answers = [];
        // Default ticket
        $price = '120.00 EUR';

        switch ($kind) {
            case 'eu_comp_ok':
                $journey = [
                    'segments' => [[ 'country' => 'DE', 'from' => 'Frankfurt(Main) Hbf', 'to' => 'Berlin Hbf', 'schedArr' => '2025-03-05T12:00:00', 'actArr' => '2025-03-05T13:20:00' ]],
                    'ticketPrice' => ['value' => '300.00 EUR'],
                    'operatorName' => ['value' => 'DB'],
                    'trainCategory' => ['value' => 'ICE'],
                    'country' => ['value' => 'DE'],
                    'is_international_inside_eu' => true,
                ];
                $answers = [ 'service_scope' => 'intl_inside_eu', 'delay_minutes_final' => 80, 'currency' => 'EUR' ];
                break;
            case 'se_regional_exempt':
                $journey = [
                    'segments' => [[ 'country' => 'SE', 'from' => 'Uppsala', 'to' => 'Stockholm C', 'schedArr' => '2025-03-12T08:48:00', 'actArr' => '2025-03-12T09:25:00' ]],
                    'ticketPrice' => ['value' => '129.00 SEK'],
                    'operatorName' => ['value' => 'SJ'],
                    'trainCategory' => ['value' => 'REG'],
                    'country' => ['value' => 'SE'],
                ];
                $answers = [ 'service_scope' => 'regional', 'delay_minutes_final' => 37, 'currency' => 'SEK' ];
                break;
            case 'sk_long_exempt':
                $journey = [
                    'segments' => [[ 'country' => 'SK', 'from' => 'Košice', 'to' => 'Bratislava hl.st.', 'schedArr' => '2025-03-15T18:05:00', 'actArr' => '2025-03-15T19:55:00' ]],
                    'ticketPrice' => ['value' => '21.90 EUR'],
                    'operatorName' => ['value' => 'ZSSK'],
                    'trainCategory' => ['value' => 'R'],
                    'country' => ['value' => 'SK'],
                    'is_long_domestic' => true,
                ];
                $answers = [ 'service_scope' => 'long_domestic', 'delay_minutes_final' => 110, 'currency' => 'EUR' ];
                break;
            default: // 'pl_beyond_eu_cancel'
                $journey = [
                    'segments' => [[ 'country' => 'PL', 'from' => 'Warszawa Centralna', 'to' => 'Lviv', 'schedArr' => '2025-03-10T15:45:00' ]],
                    'ticketPrice' => ['value' => '39.00 EUR'],
                    'operatorName' => ['value' => 'PKP Intercity'],
                    'trainCategory' => ['value' => 'IC'],
                    'country' => ['value' => 'PL'],
                    'is_international_beyond_eu' => true,
                ];
                $answers = [ 'service_scope' => 'intl_beyond_eu', 'delay_minutes_final' => 0, 'currency' => 'EUR' ];
                break;
        }

        // Simulate uploaded ticket by copying any available mock file or creating a placeholder
        $uploaded = null;
        $destDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
        $srcs = [
            ROOT . DIRECTORY_SEPARATOR . 'mocks' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'sncf_tgv_ticket.pdf',
            ROOT . DIRECTORY_SEPARATOR . 'mocks' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'se_regional_lt150.pdf',
            WWW_ROOT . 'favicon.ico',
        ];
        foreach ($srcs as $src) {
            if ($src && is_file($src)) {
                $name = 'demo_' . strtolower($kind) . '_' . basename($src);
                $dest = $destDir . DIRECTORY_SEPARATOR . $name;
                @copy($src, $dest);
                if (is_file($dest)) { $uploaded = '/files/uploads/' . $name; break; }
            }
        }
        if ($uploaded === null) {
            $name = 'demo_' . strtolower($kind) . '_' . time() . '.txt';
            $dest = $destDir . DIRECTORY_SEPARATOR . $name;
            @file_put_contents($dest, 'Demo ticket placeholder');
            $uploaded = '/files/uploads/' . $name;
        }

        return compact('journey','answers','uploaded');
    }

    private function computeClaimFromState(array $state): array
    {
        $journey = (array)($state['journey'] ?? []);
        $answers = (array)($state['answers'] ?? []);
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];
        $country = (string)($journey['country']['value'] ?? ($last['country'] ?? 'EU'));
        if (!empty($answers['country'])) {
            $country = (string)$answers['country'];
            $journey['country']['value'] = $country;
            if (!empty($journey['segments'])) { $journey['segments'][0]['country'] = $country; }
        }
        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0 EUR');
        $currency = (string)($answers['currency'] ?? '');
        if ($currency === '') {
            if (preg_match('/([A-Z]{3})/i', $priceRaw, $m)) { $currency = strtoupper($m[1]); }
        }
        $price = 0.0; if (preg_match('/([0-9]+(?:\.[0-9]{1,2})?)/', $priceRaw, $m)) { $price = (float)$m[1]; }

        $legs = [];
        foreach ($segments as $s) {
            $legs[] = [
                'from' => $s['from'] ?? '',
                'to' => $s['to'] ?? '',
                'eu' => true,
                'scheduled_dep' => $s['schedDep'] ?? '',
                'scheduled_arr' => $s['schedArr'] ?? '',
                'actual_dep' => $s['actDep'] ?? null,
                'actual_arr' => $s['actArr'] ?? null,
            ];
        }

        // Apply scope override to journey flags to ensure correct exemptions
        $scope = (string)($answers['service_scope'] ?? '');
        if ($scope !== '') {
            $journey['is_international_beyond_eu'] = $scope === 'intl_beyond_eu';
            $journey['is_international_inside_eu'] = $scope === 'intl_inside_eu';
            $journey['is_long_domestic'] = $scope === 'long_domestic';
        }

        // Recompute profile post-answers (for visibility if needed in summary template)
        $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);

        $claimInput = [
            'country_code' => $country,
            'currency' => $currency,
            'ticket_price_total' => $price,
            'trip' => [ 'through_ticket' => true, 'legs' => $legs ],
            'disruption' => [
                'delay_minutes_final' => (int)($answers['delay_minutes_final'] ?? 0),
                'notified_before_purchase' => (bool)($answers['notified_before_purchase'] ?? false),
                'extraordinary' => (bool)($answers['extraordinary'] ?? false),
                'self_inflicted' => (bool)($answers['self_inflicted'] ?? false),
            ],
            'choices' => [
                'wants_refund' => (bool)($answers['wants_refund'] ?? false),
                'wants_reroute_same_soonest' => (bool)($answers['wants_reroute_same_soonest'] ?? false),
                'wants_reroute_later_choice' => (bool)($answers['wants_reroute_later_choice'] ?? false),
            ],
            'expenses' => [
                'meals' => (float)($answers['meals'] ?? 0),
                'hotel' => (float)($answers['hotel'] ?? 0),
                'alt_transport' => (float)($answers['alt_transport'] ?? 0),
                'other' => (float)($answers['other'] ?? 0),
            ],
            'already_refunded' => 0,
        ];

        $calc = (new \App\Service\ClaimCalculator())->calculate($claimInput);
        // attach profile to state-like structure for summary view
        $this->set('profile', $profile);
        return $calc;
    }
}


===== FILE: src\Controller\ErrorController.php =====

<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.3.4
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Event\EventInterface;

/**
 * Error Handling Controller
 *
 * Controller used by ExceptionRenderer to render error responses.
 */
class ErrorController extends AppController
{
    /**
     * Initialization hook method.
     *
     * @return void
     */
    public function initialize(): void
    {
        // Only add parent::initialize() if you are confident your `AppController` is safe.
    }

    /**
     * beforeFilter callback.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event.
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
    }

    /**
     * beforeRender callback.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event.
     * @return void
     */
    public function beforeRender(EventInterface $event): void
    {
        parent::beforeRender($event);

        $this->viewBuilder()->setTemplatePath('Error');
    }

    /**
     * afterFilter callback.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event.
     * @return void
     */
    public function afterFilter(EventInterface $event): void
    {
    }
}


===== FILE: src\Controller\PagesController.php =====

<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\View\Exception\MissingTemplateException;

/**
 * Static content controller
 *
 * This controller will render views from templates/Pages/
 *
 * @link https://book.cakephp.org/5/en/controllers/pages-controller.html
 */
class PagesController extends AppController
{
    /**
     * Displays a view
     *
     * @param string ...$path Path segments.
     * @return \Cake\Http\Response|null
     * @throws \Cake\Http\Exception\ForbiddenException When a directory traversal attempt.
     * @throws \Cake\View\Exception\MissingTemplateException When the view file could not
     *   be found and in debug mode.
     * @throws \Cake\Http\Exception\NotFoundException When the view file could not
     *   be found and not in debug mode.
     * @throws \Cake\View\Exception\MissingTemplateException In debug mode.
     */
    public function display(string ...$path): ?Response
    {
        if (!$path) {
            return $this->redirect('/');
        }
        if (in_array('..', $path, true) || in_array('.', $path, true)) {
            throw new ForbiddenException();
        }
        $page = $subpage = null;

        if (!empty($path[0])) {
            $page = $path[0];
        }
        if (!empty($path[1])) {
            $subpage = $path[1];
        }
        $this->set(compact('page', 'subpage'));

        try {
            return $this->render(implode('/', $path));
        } catch (MissingTemplateException $exception) {
            if (Configure::read('debug')) {
                throw $exception;
            }
            throw new NotFoundException();
        }
    }
}


===== FILE: src\Controller\ProjectController.php =====

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


===== FILE: src\Controller\ReimbursementController.php =====

<?php
declare(strict_types=1);

namespace App\Controller;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use FPDF; // autoloaded by setasign/fpdf

class ReimbursementController extends AppController
{
    public function start(): void
    {
        // Render the form
    }

    public function generate(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $data = $this->request->is('post') ? (array)$this->request->getData() : (array)$this->request->getQueryParams();
        if ($this->request->is('get')) {
            // If user navigated directly without any params, send them to the form
            $hasAny = array_filter($data, fn($v) => $v !== null && $v !== '');
            if (empty($hasAny)) {
                $this->redirect(['action' => 'start']);
                return;
            }
        }

        // Minimal PDF summary (not filling the original PDF yet)
    $this->disableAutoRender();
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Reimbursement Claim Summary', 0, 1);

        $pdf->SetFont('Arial', '', 12);
        foreach ([
            'name' => 'Applicant Name',
            'email' => 'Email',
            'operator' => 'Railway Undertaking',
            'dep_date' => 'Scheduled departure date',
            'dep_station' => 'Departure station',
            'arr_station' => 'Destination station',
            'dep_time' => 'Scheduled departure time',
            'arr_time' => 'Scheduled arrival time',
            'train_no' => 'Train no./category',
            'ticket_no' => 'Ticket number/PNR',
            'price' => 'Ticket price',
            'actual_arrival_date' => 'Actual arrival date',
            'actual_dep_time' => 'Actual departure time',
            'actual_arr_time' => 'Actual arrival time',
            'missed_connection_station' => 'Missed connection station',
        ] as $key => $label) {
            $val = (string)($data[$key] ?? '');
            $pdf->MultiCell(0, 7, sprintf('%s: %s', $label, $val));
        }

        $pdf->Ln(5);
        $pdf->MultiCell(0, 7, 'Reason: ' . implode(', ', array_keys(array_filter([
            'delay' => !empty($data['reason_delay']),
            'cancellation' => !empty($data['reason_cancellation']),
            'missed connection' => !empty($data['reason_missed_conn']),
        ]))));

        // Output inline for now
        $this->response = $this->response->withType('pdf');
        $this->response = $this->response->withStringBody($pdf->Output('S'));
        return;
    }

    public function official(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $data = $this->request->is('post') ? (array)$this->request->getData() : (array)$this->request->getQueryParams();
        if ($this->request->is('get')) {
            $hasAny = array_filter($data, fn($v) => $v !== null && $v !== '');
            if (empty($hasAny)) {
                $this->redirect(['action' => 'start']);
                return;
            }
        }

        // Allow overriding template via query parameter for diagnostics, but only within webroot or webroot/files
        $source = null;
        $forceName = (string)($this->request->getQuery('template') ?? '');
        if ($forceName !== '') {
            $try = [WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . $forceName, WWW_ROOT . $forceName];
            foreach ($try as $p) {
                if (is_file($p)) { $source = $p; break; }
            }
        }
        if ($source === null) {
            $source = $this->findOfficialTemplatePath();
        }
        if ($source === null || !is_file($source)) {
            // Fallback to summary if template missing
            $this->disableAutoRender();
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, 'Official Form template missing', 0, 1);
            $pdf->SetFont('Arial', '', 12);
            $pdf->MultiCell(0, 7, "Looked in: webroot/files and webroot. Filenames tried include 'reimbursement_form_uncompressed.pdf' and '(reimboursement|reimbursement) form - EN - accessible.pdf' (spaces or %20).\nYou can also force a file with ?template=FILENAME.pdf");
            $this->response = $this->response->withType('pdf')->withStringBody($pdf->Output('S'));
            return;
        }

    $map = $this->loadFieldMap() ?: $this->officialFieldMap();
    $debug = (bool)$this->request->getQuery('debug');
    $dx = (float)($this->request->getQuery('dx') ?? 0);
    $dy = (float)($this->request->getQuery('dy') ?? 0);

    $this->disableAutoRender();
    $fpdi = new Fpdi('P', 'mm', 'A4');
        try {
            $pageCount = $fpdi->setSourceFile($source);
        } catch (CrossReferenceException $e) {
            // Handle compressed xref (unsupported by free parser)
            $this->disableAutoRender();
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->MultiCell(0, 8, 'Cannot import template: compressed cross-reference (XRef) stream');
            $pdf->SetFont('Arial', '', 12);
            $pdf->Ln(2);
            $pdf->MultiCell(0, 6, "This PDF uses a compression technique that the free FPDI parser can't handle. Options:\n\n1) Provide an uncompressed PDF (save as PDF 1.4 / 'reduced size').\n2) Convert locally using qpdf to disable object streams.\n3) Use the commercial fpdi-pdf-parser add-on.\n\nTried file:\n" . $source);
            $pdf->Ln(2);
            $pdf->SetFont('Courier', '', 10);
            $pdf->MultiCell(0, 5, "qpdf --qdf --object-streams=disable \"in.pdf\" \"out.pdf\"");
            $this->response = $this->response->withType('pdf')->withStringBody($pdf->Output('S'));
            return;
        } catch (\Throwable $e) {
            $this->disableAutoRender();
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->MultiCell(0, 8, 'Cannot import template');
            $pdf->SetFont('Arial', '', 12);
            $pdf->MultiCell(0, 6, 'Error: ' . $e->getMessage() . "\nFile: " . $source);
            $this->response = $this->response->withType('pdf')->withStringBody($pdf->Output('S'));
            return;
        }
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tpl = $fpdi->importPage($pageNo);
            $size = $fpdi->getTemplateSize($tpl);
            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $fpdi->useTemplate($tpl, 0, 0, $size['width'], $size['height']);

            // Optional debug grid overlay to calibrate coordinates
            if ($debug) {
                // Draw grid; offset labels to reflect dx/dy
                $this->drawDebugGrid($fpdi, (float)$size['width'], (float)$size['height']);
                if ($dx != 0 || $dy != 0) {
                    $fpdi->SetTextColor(255, 0, 0);
                    $fpdi->SetFont('Helvetica', '', 7);
                    $fpdi->SetXY(4, 4);
                    $fpdi->Cell(0, 4, sprintf('nudge dx=%.1fmm dy=%.1fmm', $dx, $dy));
                    $fpdi->SetTextColor(0, 0, 0);
                }
            }

            // Write fields for this page
            if (!empty($map[$pageNo])) {
                $fpdi->SetFont('Helvetica', '', 9);
                foreach ($map[$pageNo] as $field => $cfg) {
                    $type = $cfg['type'] ?? 'text';
                    $x = (float)$cfg['x'] + $dx;
                    $y = (float)$cfg['y'] + $dy;
                    $w = isset($cfg['w']) ? (float)$cfg['w'] : 0;
                    if ($type === 'checkbox') {
                        $checked = !empty($data[$field]);
                        if ($checked) {
                            $fpdi->SetDrawColor(0,0,0);
                            $fpdi->SetLineWidth(0.3);
                            // Draw X in ~4mm box
                            $fpdi->Line($x, $y, $x+4, $y+4);
                            $fpdi->Line($x, $y+4, $x+4, $y);
                        }
                        continue;
                    }
                    $srcField = $cfg['source'] ?? $field;
                    $val = (string)($data[$srcField] ?? '');
                    if ($val === '') { continue; }
                    $fpdi->SetXY($x, $y);
                    if (!empty($cfg['multiline'])) {
                        $fpdi->MultiCell($w > 0 ? $w : 100, 4, $val);
                    } else {
                        $fpdi->Cell($w > 0 ? $w : 0, 4, $val, 0, 0);
                    }
                }
            }
        }

        $this->response = $this->response->withType('pdf')->withStringBody($fpdi->Output('S'));
        return;
    }

    /**
     * Minimal coordinate map for key fields on the official form.
     * Coordinates are in mm relative to the top-left of each page.
     * Adjust iteratively by viewing the output.
     * @return array<int,array<string,array{x:float,y:float,w?:float,multiline?:bool}>>
     */
    private function officialFieldMap(): array
    {
        return [
            1 => [
                'name' => ['x' => 30, 'y' => 40, 'w' => 80, 'type' => 'text'],
                'email' => ['x' => 130, 'y' => 40, 'w' => 60, 'type' => 'text'],
                'operator' => ['x' => 30, 'y' => 55, 'w' => 80, 'type' => 'text'],
                'train_no' => ['x' => 130, 'y' => 55, 'w' => 60, 'type' => 'text'],
                'dep_station' => ['x' => 30, 'y' => 70, 'w' => 80, 'type' => 'text'],
                'arr_station' => ['x' => 130, 'y' => 70, 'w' => 60, 'type' => 'text'],
                'dep_date' => ['x' => 30, 'y' => 85, 'w' => 40, 'type' => 'text'],
                'arr_time' => ['x' => 130, 'y' => 85, 'w' => 40, 'type' => 'text'],
                'ticket_no' => ['x' => 30, 'y' => 100, 'w' => 160, 'type' => 'text'],
                'price' => ['x' => 30, 'y' => 115, 'w' => 40, 'type' => 'text'],
                'actual_arrival_date' => ['x' => 30, 'y' => 130, 'w' => 40, 'type' => 'text'],
                'missed_connection_station' => ['x' => 30, 'y' => 145, 'w' => 160, 'multiline' => true, 'type' => 'text'],
            ],
        ];
    }

    /**
     * Attempt to locate the official template PDF, handling filenames with spaces or literal %20,
     * and both reimbursement/reimboursement spellings.
     */
    private function findOfficialTemplatePath(): ?string
    {
        $dirs = [
            WWW_ROOT . 'files' . DIRECTORY_SEPARATOR,
            WWW_ROOT,
        ];
        // Prefer locally converted/uncompressed files first
        $candidates = [
            'reimbursement_form_uncompressed.pdf',
            'reimbursement_form_converted.pdf',
            // then the known official names (may be compressed)
            'reimboursement form - EN - accessible.pdf',
            'reimboursement%20form%20-%20EN%20-%20accessible.pdf',
            'reimbursement form - EN - accessible.pdf',
            'reimbursement%20form%20-%20EN%20-%20accessible.pdf',
        ];
        foreach ($dirs as $dir) {
            foreach ($candidates as $file) {
                $p = $dir . $file;
                if (is_file($p)) { return $p; }
            }
        }
        // Fallback glob scan in both locations
        foreach ($dirs as $dir) {
            $patterns = [
                $dir . '*reimbours*form*EN*accessible*.pdf',
                $dir . '*reimburs*form*EN*accessible*.pdf',
            ];
            foreach ($patterns as $glob) {
                $hits = glob($glob) ?: [];
                if (!empty($hits)) { return $hits[0]; }
            }
        }
        return null;
    }

    /**
     * Load a field map from config/pdf/reimbursement_map.json if present.
     * @return array<int,array<string,array<string,mixed>>>|null
     */
    private function loadFieldMap(): ?array
    {
        $path = CONFIG . 'pdf' . DIRECTORY_SEPARATOR . 'reimbursement_map.json';
        if (!is_file($path)) {
            return null;
        }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Draw a light grid to calibrate coordinates (in mm).
     */
    private function drawDebugGrid(Fpdi $pdf, float $w, float $h): void
    {
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetFont('Helvetica', '', 6);
        for ($x = 0; $x <= $w; $x += 10) {
            $pdf->Line($x, 0, $x, $h);
            if ($x % 20 === 0) { $pdf->SetXY($x + 1, 2); $pdf->Cell(8, 3, (string)$x, 0, 0); }
        }
        for ($y = 0; $y <= $h; $y += 10) {
            $pdf->Line(0, $y, $w, $y);
            if ($y % 20 === 0) { $pdf->SetXY(2, $y + 1); $pdf->Cell(8, 3, (string)$y, 0, 0); }
        }
    }
}


===== FILE: src\Controller\UploadController.php =====

<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Filesystem\File;
use Cake\Utility\Text;
use App\Service\ExemptionProfileBuilder;

class UploadController extends AppController
{
    public function index(): void
    {
        // Renders templates/Upload/index.php
    }

    public function analyze(): void
    {
        $this->request->allowMethod(['post']);
        $file = $this->request->getData('ticket');
        $extJourneyRaw = $this->request->getData('journey'); // optional JSON pasted (string)

        $journey = [];
        $errors = [];

        // If user pasted JSON journey, use that (developer shortcut)
        if (is_string($extJourneyRaw) && trim($extJourneyRaw) !== '') {
            $decoded = json_decode($extJourneyRaw, true);
            if (is_array($decoded)) {
                $journey = $decoded;
            } else {
                $errors[] = 'Ugyldigt JSON i Journey-feltet.';
            }
        }

        // Handle file upload (image/pdf/pkpass) – store only; real parsing can be added later
        $savedPath = null;
        if ($file && is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = $file['tmp_name'];
            $name = $file['name'] ?? ('ticket_' . Text::uuid());
            $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$name) ?: ('ticket_' . Text::uuid());
            $destDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads';
            if (!is_dir($destDir)) { mkdir($destDir, 0775, true); }
            $dest = $destDir . DIRECTORY_SEPARATOR . $safe;
            if (@move_uploaded_file($tmp, $dest)) {
                $savedPath = $dest;
            } else {
                $errors[] = 'Kunne ikke gemme den uploadede fil';
            }
        }

        // Minimal placeholder: if no journey provided, create a single-segment journey using a heuristic country hint
        if (empty($journey)) {
            $countryHint = $this->request->getData('country') ?: 'FR';
            $journey = [
                'segments' => [[ 'country' => (string)$countryHint ]],
                'is_international_inside_eu' => false,
                'is_international_beyond_eu' => false,
                'is_long_domestic' => false,
            ];
        }

        // Compute exemptions profile (focus på Art. 12 til at starte med)
        $builder = new ExemptionProfileBuilder();
        $profile = $builder->build($journey);
        $art12_applies = (bool)($profile['articles']['art12'] ?? true);

        // Run evaluators for a quick end-to-end summary
        $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, []);
        $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, []);
        $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, ['refundAlready' => false]);
        $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, []);

        // Build a minimal ClaimInput from the journey (best-effort until OCR/mocks er koblet på)
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];
        $country = (string)($journey['country']['value'] ?? ($last['country'] ?? 'EU'));
        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0 EUR');
        $currency = 'EUR';
        if (preg_match('/([A-Z]{3})/i', $priceRaw, $m)) { $currency = strtoupper($m[1]); }
        $price = 0.0;
        if (preg_match('/([0-9]+(?:\.[0-9]{1,2})?)/', $priceRaw, $m)) { $price = (float)$m[1]; }
        $legs = [];
        foreach ($segments as $s) {
            $legs[] = [
                'from' => $s['from'] ?? '',
                'to' => $s['to'] ?? '',
                'eu' => true,
                'scheduled_dep' => $s['schedDep'] ?? '',
                'scheduled_arr' => $s['schedArr'] ?? '',
                'actual_dep' => $s['actDep'] ?? null,
                'actual_arr' => $s['actArr'] ?? null,
            ];
        }
        $claimInput = [
            'country_code' => $country,
            'currency' => $currency,
            'ticket_price_total' => $price,
            'trip' => [ 'through_ticket' => true, 'legs' => $legs ],
            'disruption' => [
                'delay_minutes_final' => $this->computeDelayFromJourney($journey),
                'notified_before_purchase' => false,
                'extraordinary' => false,
                'self_inflicted' => false,
            ],
            'choices' => [ 'wants_refund' => false, 'wants_reroute_same_soonest' => false, 'wants_reroute_later_choice' => false ],
            'expenses' => [ 'meals' => 0, 'hotel' => 0, 'alt_transport' => 0, 'other' => 0 ],
            'already_refunded' => 0,
        ];
        $claim = (new \App\Service\ClaimCalculator())->calculate($claimInput);

        $this->set(compact('profile', 'art12_applies', 'art12', 'art9', 'refund', 'refusion', 'claim', 'savedPath', 'errors'));
        $this->viewBuilder()->setTemplate('result');
    }

    /** Compute delay minutes from a Journey-like array */
    private function computeDelayFromJourney(array $journey): int
    {
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];
        $schedArr = (string)($last['schedArr'] ?? '');
        $actArr = (string)($last['actArr'] ?? '');
        if ($schedArr !== '' && $actArr !== '') {
            $t1 = strtotime($schedArr); $t2 = strtotime($actArr);
            if ($t1 && $t2) { return max(0, (int)round(($t2 - $t1)/60)); }
        }
        // Fallback fields
        $depDate = (string)($journey['depDate']['value'] ?? '');
        $sched = (string)($journey['schedArrTime']['value'] ?? '');
        $act = (string)($journey['actualArrTime']['value'] ?? '');
        if ($depDate && $sched && $act) {
            $t1 = strtotime($depDate . 'T' . $sched . ':00');
            $t2 = strtotime(($journey['actualArrDate']['value'] ?? $depDate) . 'T' . $act . ':00');
            if ($t1 && $t2) { return max(0, (int)round(($t2 - $t1)/60)); }
        }
        return 0;
    }
}


===== FILE: src\View\AjaxView.php =====

<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.4
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\View;

/**
 * A view class that is used for AJAX responses.
 * Currently only switches the default layout and sets the response type -
 * which just maps to text/html by default.
 */
class AjaxView extends AppView
{
    /**
     * The name of the layout file to render the view inside of. The name
     * specified is the filename of the layout in /templates/Layout without
     * the .php extension.
     *
     * @var string
     */
    protected string $layout = 'ajax';

    /**
     * Initialization hook method.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->response = $this->response->withType('ajax');
    }
}


===== FILE: src\View\AppView.php =====

<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.0.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\View;

use Cake\View\View;

/**
 * Application View
 *
 * Your application's default view class
 *
 * @link https://book.cakephp.org/5/en/views.html#the-app-view
 */
class AppView extends View
{
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like adding helpers.
     *
     * e.g. `$this->addHelper('Html');`
     *
     * @return void
     */
    public function initialize(): void
    {
    }
}


===== FILE: templates\Admin\Claims\index.php =====

<?php
/** @var \App\View\AppView $this */
/** @var iterable $claims */
?>
<div class="content">
  <h1>Sager</h1>
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>Sagsnr</th>
        <th>Klient</th>
        <th>Operatør</th>
        <th>Forsinkelse</th>
        <th>Komp%</th>
        <th>Udbetaling</th>
        <th>Oprettet</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($claims as $c): ?>
      <tr>
        <td><?= (int)$c->id ?></td>
        <td><?= h($c->case_number) ?></td>
        <td><?= h($c->client_name) ?> (<?= h($c->client_email) ?>)</td>
        <td><?= h($c->operator) ?></td>
        <td><?= (int)$c->delay_min ?> min</td>
        <td><?= (int)$c->computed_percent ?>%</td>
        <td><?= number_format((float)$c->payout_amount, 2) . ' ' . h($c->currency) ?></td>
        <td><?= h($c->created) ?></td>
        <td><?= $this->Html->link('Vis', ['action' => 'view', $c->id]) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>


===== FILE: templates\Admin\Claims\view.php =====

<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\Claim $claim */
?>
<div class="content">
  <h1>Sag <?= h($claim->case_number) ?></h1>
  <dl>
    <dt>Klient</dt>
    <dd><?= h($claim->client_name) ?> (<?= h($claim->client_email) ?>)</dd>
    <dt>Operatør / Produkt</dt>
    <dd><?= h($claim->operator) ?> / <?= h($claim->product) ?></dd>
    <dt>Forsinkelse</dt>
    <dd><?= (int)$claim->delay_min ?> min</dd>
    <dt>Kompensation</dt>
    <dd><?= (int)$claim->computed_percent ?>% (<?= h($claim->computed_source) ?>) → <?= number_format((float)$claim->compensation_amount, 2) ?> <?= h($claim->currency) ?></dd>
    <dt>Vores honorar</dt>
    <dd><?= (int)$claim->fee_percent ?>% → <?= number_format((float)$claim->fee_amount, 2) ?> <?= h($claim->currency) ?></dd>
    <dt>Udbetaling</dt>
    <dd><?= number_format((float)$claim->payout_amount, 2) ?> <?= h($claim->currency) ?></dd>
  <?php if (!empty($claim->assignment_pdf)): ?>
  <dt>Overdragelsesdokument</dt>
  <dd><a href="/<?= h($claim->assignment_pdf) ?>" target="_blank" rel="noopener">Download PDF</a></dd>
  <?php endif; ?>
    <dt>Status</dt>
    <dd><?= h($claim->status) ?></dd>
    <dt>Oprettet</dt>
    <dd><?= h($claim->created) ?></dd>
  </dl>
  <h3>Opdater status</h3>
  <?= $this->Form->create(null, ['url' => ['action' => 'updateStatus', $claim->id]]) ?>
    <?= $this->Form->control('status', ['label' => 'Status', 'value' => $claim->status]) ?>
    <?= $this->Form->control('notes', ['label' => 'Noter', 'type' => 'textarea', 'value' => $claim->notes]) ?>
    <?= $this->Form->button('Gem status') ?>
  <?= $this->Form->end() ?>

  <h3>Markér som betalt</h3>
  <?= $this->Form->create(null, ['url' => ['action' => 'markPaid', $claim->id]]) ?>
    <?= $this->Form->control('payout_reference', ['label' => 'Udbetalingsreference']) ?>
    <?= $this->Form->button('Markér betalt') ?>
  <?= $this->Form->end() ?>

  <?php if (!empty($claim->claim_attachments)): ?>
    <h3>Bilag</h3>
    <ul>
      <?php foreach ($claim->claim_attachments as $att): ?>
        <li>[<?= h($att->type) ?>] <a href="/<?= h($att->path) ?>" target="_blank" rel="noopener"><?= h($att->original_name) ?></a> (<?= (int)$att->size ?> bytes)</li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p><?= $this->Html->link('Tilbage til oversigt', ['action' => 'index']) ?></p>
</div>


===== FILE: templates\Claims\result.php =====

<?php
/** @var \App\View\AppView $this */
/** @var array $result */
/** @var array $ctx */
?>
<div class="content">
  <h1>Resultat</h1>
  <p><strong>Kompensationsprocent:</strong> <?= (int)($result['percent'] ?? 0) ?>%</p>
  <p><strong>Kilde:</strong> <?= h($result['source'] ?? 'eu') ?></p>
  <?php if (!empty($result['notes'])): ?>
    <p><strong>Note:</strong> <?= h($result['notes']) ?></p>
  <?php endif; ?>

  <h3>Input</h3>
  <pre><?= h(var_export($ctx, true)) ?></pre>

  <p><?= $this->Html->link('Tilbage', ['action' => 'start']) ?></p>
</div>


===== FILE: templates\Claims\start.php =====

<?php
/** @var \App\View\AppView $this */
?>
<div class="content">
  <h1>Start kravberegning</h1>
  <p>Indtast et par felter for at beregne EU/national kompensation. Dette er en simpel demo – den kan udvides til fuld formular.</p>
  <?= $this->Form->create(null, ['url' => ['action' => 'compute']]) ?>
    <?= $this->Form->control('delay_min', ['label' => 'Forsinkelse (minutter)', 'type' => 'number', 'min' => 0]) ?>
    <?= $this->Form->control('refund_already', ['label' => 'Refusion allerede udbetalt', 'type' => 'checkbox']) ?>
    <?= $this->Form->control('known_delay_before_purchase', ['label' => 'Forsinkelsen var kendt før køb', 'type' => 'checkbox']) ?>
    <?= $this->Form->control('extraordinary', ['label' => 'Ekstraordinære forhold', 'type' => 'checkbox']) ?>
    <?= $this->Form->control('self_inflicted', ['label' => 'Selvforskyldt', 'type' => 'checkbox']) ?>
    <?= $this->Form->control('country', ['label' => 'Land (fx France, Spain, Sweden)']) ?>
    <?= $this->Form->control('operator', ['label' => 'Operatør (fx SNCF, Renfe, SJ)']) ?>
    <?= $this->Form->control('product', ['label' => 'Produkt (fx TGV INOUI/Intercités, AVE, Inrikes)']) ?>
    <?= $this->Form->button('Beregn') ?>
  <?= $this->Form->end() ?>

  <p>Dokumenter: <?= $this->Html->link('Flow charts', ['controller' => 'Project', 'action' => 'index']) ?></p>
</div>


===== FILE: templates\ClientClaims\start.php =====

<?php
/** @var \App\View\AppView $this */
?>
<div class="content">
  <h1>Start din sag</h1>
  <p>Udfyld felterne – vi beregner kompensation med det samme og udbetaler straks, hvorefter vi overtager sagen.</p>
  <?= $this->Form->create(null, ['url' => ['action' => 'submit'], 'type' => 'file']) ?>
    <fieldset>
      <legend>Dine oplysninger</legend>
      <?= $this->Form->control('name', ['label' => 'Navn', 'required' => true]) ?>
      <?= $this->Form->control('email', ['label' => 'Email', 'required' => true]) ?>
    </fieldset>
    <fieldset>
      <legend>Bilag</legend>
      <?= $this->Form->control('ticket_file', ['type' => 'file', 'label' => 'Billet (PDF/PNG/JPG)']) ?>
      <?= $this->Form->control('receipts_file', ['type' => 'file', 'label' => 'Kvitteringer (PDF/PNG/JPG)']) ?>
      <?= $this->Form->control('delay_confirmation_file', ['type' => 'file', 'label' => 'Bekræftelse på forsinkelse (Art. 20(4))']) ?>
    </fieldset>
    <fieldset>
      <legend>Rejsen</legend>
      <?= $this->Form->control('country', ['label' => 'Land']) ?>
      <?= $this->Form->control('operator', ['label' => 'Operatør']) ?>
      <?= $this->Form->control('product', ['label' => 'Produkt']) ?>
      <?= $this->Form->control('delay_min', ['label' => 'Forsinkelse (minutter)', 'type' => 'number', 'min' => 0]) ?>
      <?= $this->Form->control('ticket_price', ['label' => 'Billetpris', 'type' => 'number', 'step' => '0.01']) ?>
      <?= $this->Form->control('currency', ['label' => 'Valuta', 'value' => 'EUR']) ?>
    </fieldset>
    <fieldset>
      <legend>Checks</legend>
      <?= $this->Form->control('refund_already', ['label' => 'Refusion allerede udbetalt', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('known_delay_before_purchase', ['label' => 'Forsinkelsen var kendt før køb', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('extraordinary', ['label' => 'Ekstraordinære forhold', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('self_inflicted', ['label' => 'Selvforskyldt', 'type' => 'checkbox']) ?>
    </fieldset>
    <fieldset>
      <legend>Overdragelse</legend>
      <p>Jeg accepterer, at I overtager sagen, og at jeg modtager min udbetaling nu mod et honorar (se beregning).</p>
      <?= $this->Form->control('assignment_accepted', ['label' => 'Jeg accepterer overdragelsen', 'type' => 'checkbox', 'required' => true]) ?>
    </fieldset>
    <?= $this->Form->button('Indsend & få tilbud nu') ?>
  <?= $this->Form->end() ?>
</div>


===== FILE: templates\ClientClaims\submitted.php =====

<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\Claim $claim */
?>
<div class="content">
  <h1>Tak! Din sag er modtaget</h1>
  <p><strong>Sagsnummer:</strong> <?= h($claim->case_number) ?></p>
  <p><strong>Forventet kompensation (beregnet):</strong> <?= number_format((float)$claim->compensation_amount, 2) . ' ' . h($claim->currency) ?> (<?= (int)$claim->computed_percent ?>% via <?= h($claim->computed_source) ?>)</p>
  <p><strong>Vores honorar:</strong> <?= number_format((float)$claim->fee_amount, 2) . ' ' . h($claim->currency) ?> (<?= (int)$claim->fee_percent ?>%)</p>
  <p><strong>Udbetaling nu:</strong> <?= number_format((float)$claim->payout_amount, 2) . ' ' . h($claim->currency) ?></p>
  <p>Vi kontakter dig på <?= h($claim->client_email) ?>. Du kan svarere denne mail, hvis du vil tilføje bilag eller oplysninger.</p>
  <p><?= $this->Html->link('Til forsiden', ['controller' => 'Pages', 'action' => 'display', 'home']) ?></p>
</div>


===== FILE: templates\ClientWizard\expenses.php =====

<?php
/** @var \App\View\AppView $this */
/** @var array $state */
?>
<div class="content">
  <h1>Udgifter (Art. 20)</h1>
  <p>Indtast dokumenterede udgifter. Du kan uploade bilag senere.</p>
  <?= $this->Form->create() ?>
    <fieldset>
      <legend>Valuta og beløb</legend>
      <?= $this->Form->control('currency', ['label' => 'Valuta', 'value' => 'EUR']) ?>
      <?= $this->Form->control('meals', ['label' => 'Mad og drikke', 'type' => 'number', 'step' => '0.01', 'value' => 0]) ?>
      <?= $this->Form->control('hotel', ['label' => 'Hotel', 'type' => 'number', 'step' => '0.01', 'value' => 0]) ?>
      <?= $this->Form->control('alt_transport', ['label' => 'Alternativ transport (taxa/bus)', 'type' => 'number', 'step' => '0.01', 'value' => 0]) ?>
      <?= $this->Form->control('other', ['label' => 'Andet', 'type' => 'number', 'step' => '0.01', 'value' => 0]) ?>
    </fieldset>
    <?= $this->Form->button('Se opsummering') ?>
  <?= $this->Form->end() ?>
</div>


===== FILE: templates\ClientWizard\questions.php =====

<?php
/** @var \App\View\AppView $this */
/** @var array $state */
?>
<div class="content">
  <h1>Supplerende spørgsmål</h1>
  <p>Vi mangler lidt data for at vurdere Art. 12/9/18/19/20. Udfyld venligst nedenfor.</p>
  <?= $this->Form->create() ?>
    <fieldset>
      <legend>Land</legend>
      <?= $this->Form->control('country', [
        'label' => 'Primært land (for matrix)',
        'type' => 'text',
        'placeholder' => 'FR/DE/SE/SK/PL …'
      ]) ?>
    </fieldset>
    <fieldset>
      <legend>Tjenestetype (scope)</legend>
      <?= $this->Form->control('service_scope', [
        'label' => 'Vælg det der passer bedst',
        'options' => [
          'regional' => 'Regional/by/forstad',
          'long_domestic' => 'Langdistance (indenrigs)',
          'intl_inside_eu' => 'International inden for EU',
          'intl_beyond_eu' => 'International ud over EU',
        ],
        'empty' => '— vælg —'
      ]) ?>
    </fieldset>
    <fieldset>
      <legend>Art. 12 (gennemgående billet)</legend>
      <?= $this->Form->control('through_ticket_disclosure', ['label' => 'Er det en gennemgående billet?', 'options' => ['Gennemgående' => 'Gennemgående', 'Særskilte' => 'Særskilte', 'unknown' => 'Ved ikke'], 'default' => 'unknown']) ?>
      <?= $this->Form->control('separate_contract_notice', ['label' => 'Oplyst separate kontrakter?', 'options' => ['Ja'=>'Ja','Nej'=>'Nej','unknown'=>'Ved ikke'],'default'=>'unknown']) ?>
    </fieldset>
    <fieldset>
      <legend>Art. 9 (information)</legend>
      <?= $this->Form->control('info_before_purchase', ['label' => 'Information før køb', 'options' => ['Ja'=>'Ja','Nej'=>'Nej','Delvist'=>'Delvist','unknown'=>'Ved ikke'], 'default'=>'unknown']) ?>
      <?= $this->Form->control('info_on_rights', ['label' => 'Oplysning om rettigheder', 'options' => ['Ja','Nej','Delvist','unknown'], 'default' => 'unknown']) ?>
      <?= $this->Form->control('info_during_disruption', ['label' => 'Info under afbrydelse', 'options' => ['Ja','Nej','Delvist','unknown'], 'default' => 'unknown']) ?>
      <?= $this->Form->control('language_accessible', ['label' => 'Tilgængeligt sprog', 'options' => ['Ja','Nej','Delvist','unknown'], 'default' => 'unknown']) ?>
    </fieldset>
    <fieldset>
      <legend>Forsinkelse / Årsager</legend>
      <?= $this->Form->control('delay_minutes_final', ['label' => 'Forsinkelse ved destination (min)', 'type' => 'number', 'min' => 0, 'value' => 0]) ?>
      <?= $this->Form->control('notified_before_purchase', ['label' => 'Vidste du om forsinkelsen før køb?', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('extraordinary', ['label' => 'Ekstraordinære omstændigheder?', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('self_inflicted', ['label' => 'Selvforskyldt?', 'type' => 'checkbox']) ?>
    </fieldset>
    <fieldset>
      <legend>Valg (Art. 18)</legend>
      <?= $this->Form->control('wants_refund', ['label' => 'Vil du have refusion af billet?', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('wants_reroute_same_soonest', ['label' => 'Rerouting hurtigst muligt (samme forhold)?', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('wants_reroute_later_choice', ['label' => 'Rerouting senere efter eget valg?', 'type' => 'checkbox']) ?>
    </fieldset>
    <?= $this->Form->button('Fortsæt til udgifter') ?>
  <?= $this->Form->end() ?>
</div>


===== FILE: templates\ClientWizard\start.php =====

<?php
/** @var \App\View\AppView $this */
/** @var array $state */
?>
<div class="content">
  <h1>Start din sag</h1>
  <p>Upload din billet (foto/PDF) eller indsæt en JSON-udgave af rejsen. Vi spørger efter manglende detaljer på næste trin.</p>
  <?= $this->Form->create(null, ['type' => 'file']) ?>
    <fieldset>
      <legend>Billet</legend>
      <?= $this->Form->control('ticket', ['type' => 'file', 'label' => 'Billede/PDF']) ?>
      <?= $this->Form->control('country', ['label' => 'Land (hint hvis parsing mangler)', 'value' => 'FR']) ?>
      <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
        <div style="min-width:200px;">
          <?= $this->Form->control('ticket_amount', ['label' => 'Billetpris beløb', 'type' => 'number', 'step' => '0.01', 'placeholder' => '120.00']) ?>
        </div>
        <div style="min-width:120px;">
          <?= $this->Form->control('ticket_currency', ['label' => 'Valuta', 'type' => 'text', 'value' => 'EUR']) ?>
        </div>
      </div>
      <small style="color:#666;">Alternativt kan du udfylde et felt nedenfor i ét: "Billetpris (fx 120.00 EUR)"</small>
      <?= $this->Form->control('ticket_price', ['label' => 'Billetpris (fritekst)']) ?>
    </fieldset>
    <details style="margin:10px 0;">
      <summary>Udvikler-genvej: Indsæt Journey JSON</summary>
      <textarea name="journey" style="width:100%;height:140px;" placeholder='{"segments":[{"country":"FR"}],"ticketPrice":{"value":"120.00 EUR"}}'></textarea>
    </details>
    <?= $this->Form->button('Fortsæt') ?>
  <?= $this->Form->end() ?>

  <hr>
  <h3>Hurtig test</h3>
  <p>Autofyld en realistisk demo-sag (inkl. simuleret upload) for at teste hele flowet.</p>
  <?= $this->Form->create() ?>
    <input type="hidden" name="autofill" value="1">
    <?= $this->Form->button('Autofyld demo-sag') ?>
  <?= $this->Form->end() ?>
</div>


===== FILE: templates\ClientWizard\submitted.php =====

<?php
/** @var \App\View\AppView $this */
/** @var array $calc */
?>
<div class="content">
  <h1>Tak – din sag er modtaget</h1>
  <p>Vi har modtaget din sag. Hvis du har valgt øjeblikkelig udbetaling, vil du modtage pengene kort efter.</p>
  <h3>Opsummering</h3>
  <ul>
    <li>Brutto: <?= h(number_format((float)($calc['totals']['gross_claim'] ?? 0), 2)) ?> <?= h($calc['totals']['currency'] ?? 'EUR') ?></li>
    <li>Servicefee 25%: <?= h(number_format((float)($calc['totals']['service_fee_amount'] ?? 0), 2)) ?></li>
    <li>Netto til klient: <strong><?= h(number_format((float)($calc['totals']['net_to_client'] ?? 0), 2)) ?></strong></li>
  </ul>
  <p><?= $this->Html->link('Start ny sag', ['action' => 'start']) ?></p>
</div>


===== FILE: templates\ClientWizard\summary.php =====

<?php
/** @var \App\View\AppView $this */
/** @var array $state */
/** @var array $calc */
?>
<div class="content">
  <h1>Opsummering</h1>
  <p>Gennemse beregningen og bekræft overdragelsen så vi kan udbetale.</p>
  <h3>Totals</h3>
  <ul>
    <li>Billetpris (basis): <?= h($state['journey']['ticketPrice']['value'] ?? '–') ?></li>
    <li>Forsinkelse anvendt: <?= h((string)($state['answers']['delay_minutes_final'] ?? 0)) ?> min</li>
  <li>Art. 19 aktiv? <?= isset($profile['articles']['art19']) ? ($profile['articles']['art19'] ? 'Ja' : 'Nej (exempt)') : (isset($state['profile']['articles']['art19']) ? ($state['profile']['articles']['art19'] ? 'Ja' : 'Nej (exempt)') : 'ukendt') ?></li>
    <li>Refund (Art. 18): <?= h(number_format((float)($calc['breakdown']['refund']['amount'] ?? 0), 2)) ?> <?= h($calc['totals']['currency'] ?? 'EUR') ?></li>
    <li>Kompensation (Art. 19): <?= h(number_format((float)($calc['breakdown']['compensation']['amount'] ?? 0), 2)) ?> (<?= h(($calc['breakdown']['compensation']['pct'] ?? 0) . '%') ?>)</li>
    <li>Kompensation regel: <?= h($calc['breakdown']['compensation']['source'] ?? 'eu') ?><?= isset($calc['breakdown']['compensation']['notes']) && $calc['breakdown']['compensation']['notes'] !== '' ? ' — ' . h($calc['breakdown']['compensation']['notes']) : '' ?></li>
    <li>Udgifter (Art. 20): <?= h(number_format((float)($calc['breakdown']['expenses']['total'] ?? 0), 2)) ?></li>
    <li>Servicefee 25%: <?= h(number_format((float)($calc['totals']['service_fee_amount'] ?? 0), 2)) ?></li>
    <li>Netto til klient: <strong><?= h(number_format((float)($calc['totals']['net_to_client'] ?? 0), 2)) ?></strong></li>
  </ul>
  <?php if (isset($profile['articles']['art19']) && !$profile['articles']['art19']): ?>
    <div class="message warning" style="background:#fff3cd;border:1px solid #ffeeba;padding:10px;margin:10px 0;">
      EU-kompensation (Art. 19) er undtaget for den valgte tjeneste/land. Vi forsøger automatisk national/operatør-ordning hvor relevant.
    </div>
  <?php endif; ?>
  <details>
    <summary>Detaljer</summary>
    <pre style="background:#f8f8f8;border:1px solid #eee;padding:10px;white-space:pre-wrap;"><?= h(json_encode($calc, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
  </details>

  <?= $this->Form->create() ?>
    <fieldset>
      <legend>Dine oplysninger</legend>
      <?= $this->Form->control('name', ['label' => 'Navn']) ?>
      <?= $this->Form->control('email', ['label' => 'Email']) ?>
      <label style="display:block;margin-top:10px;">
        <input type="checkbox" name="assignment_accepted" value="1"> Jeg accepterer overdragelse af kravet og udbetaling netto af 25% fee.
      </label>
    </fieldset>
    <?= $this->Form->button('Bekræft og send') ?>
  <?= $this->Form->end() ?>
</div>


===== FILE: templates\element\flash\default.php =====

<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
$class = 'message';
if (!empty($params['class'])) {
    $class .= ' ' . $params['class'];
}
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="<?= h($class) ?>" onclick="this.classList.add('hidden');"><?= $message ?></div>


===== FILE: templates\element\flash\error.php =====

<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="message error" onclick="this.classList.add('hidden');"><?= $message ?></div>


===== FILE: templates\element\flash\info.php =====

<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="message" onclick="this.classList.add('hidden');"><?= $message ?></div>


===== FILE: templates\element\flash\success.php =====

<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="message success" onclick="this.classList.add('hidden')"><?= $message ?></div>


===== FILE: templates\element\flash\warning.php =====

<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="message warning" onclick="this.classList.add('hidden');"><?= $message ?></div>


===== FILE: templates\email\html\default.php =====

<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \Cake\View\View $this
 * @var string $content
 */

$lines = explode("\n", $content);

foreach ($lines as $line) :
    echo '<p> ' . $line . "</p>\n";
endforeach;


===== FILE: templates\email\text\default.php =====

<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \Cake\View\View $this
 * @var string $content
 */

echo $content;


===== FILE: templates\Error\error400.php =====

<?php
/**
 * @var \App\View\AppView $this
 * @var string $message
 * @var string $url
 */
use Cake\Core\Configure;

$this->layout = 'error';

if (Configure::read('debug')) :
    $this->layout = 'dev_error';

    $this->assign('title', $message);
    $this->assign('templateName', 'error400.php');

    $this->start('file');
    echo $this->element('auto_table_warning');
    $this->end();
endif;
?>
<h2><?= h($message) ?></h2>
<p class="error">
    <strong><?= __d('cake', 'Error') ?>: </strong>
    <?= __d('cake', 'The requested address {0} was not found on this server.', "<strong>'{$url}'</strong>") ?>
</p>


===== FILE: templates\Error\error500.php =====

<?php
/**
 * @var \App\View\AppView $this
 * @var string $message
 * @var string $url
 */
use Cake\Core\Configure;
use Cake\Error\Debugger;

$this->layout = 'error';

if (Configure::read('debug')) :
    $this->layout = 'dev_error';

    $this->assign('title', $message);
    $this->assign('templateName', 'error500.php');

    $this->start('file');
?>
<?php if ($error instanceof Error) : ?>
    <?php $file = $error->getFile() ?>
    <?php $line = $error->getLine() ?>
    <strong>Error in: </strong>
    <?= $this->Html->link(sprintf('%s, line %s', Debugger::trimPath($file), $line), Debugger::editorUrl($file, $line)); ?>
<?php endif; ?>
<?php
    echo $this->element('auto_table_warning');

    $this->end();
endif;
?>
<h2><?= __d('cake', 'An Internal Error Has Occurred.') ?></h2>
<p class="error">
    <strong><?= __d('cake', 'Error') ?>: </strong>
    <?= h($message) ?>
</p>


===== FILE: templates\layout\ajax.php =====

<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */

echo $this->fetch('content');


===== FILE: templates\layout\default.php =====

<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */

$cakeDescription = 'CakePHP: the rapid development php framework';
?>
<!DOCTYPE html>
<html>
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        <?= $cakeDescription ?>:
        <?= $this->fetch('title') ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->Html->css(['normalize.min', 'milligram.min', 'fonts', 'cake']) ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
</head>
<body>
    <nav class="top-nav">
        <div class="top-nav-title">
            <a href="<?= $this->Url->build('/') ?>"><span>Cake</span>PHP</a>
        </div>
        <div class="top-nav-links">
            <a target="_blank" rel="noopener" href="https://book.cakephp.org/5/">Documentation</a>
            <a target="_blank" rel="noopener" href="https://api.cakephp.org/">API</a>
        </div>
    </nav>
    <main class="main">
        <div class="container">
            <?= $this->Flash->render() ?>
            <?= $this->fetch('content') ?>
        </div>
    </main>
    <footer>
    </footer>
</body>
</html>


===== FILE: templates\layout\email\html\default.php =====

<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html>
<head>
    <title><?= $this->fetch('title') ?></title>
</head>
<body>
    <?= $this->fetch('content') ?>
</body>
</html>


===== FILE: templates\layout\email\text\default.php =====

<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */

echo $this->fetch('content');


===== FILE: templates\layout\error.php =====

<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */
?>
<!DOCTYPE html>
<html>
<head>
    <?= $this->Html->charset() ?>
    <title>
        <?= $this->fetch('title') ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->Html->css(['normalize.min', 'milligram.min', 'fonts', 'cake']) ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
</head>
<body>
    <div class="error-container">
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
        <?= $this->Html->link(__('Back'), 'javascript:history.back()') ?>
    </div>
</body>
</html>


===== FILE: templates\Pages\home.php =====

<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.10.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Error\Debugger;
use Cake\Http\Exception\NotFoundException;

$this->disableAutoLayout();

$checkConnection = function (string $name) {
    $error = null;
    $connected = false;
    try {
        ConnectionManager::get($name)->getDriver()->connect();
        // No exception means success
        $connected = true;
    } catch (Exception $connectionError) {
        $error = $connectionError->getMessage();
        if (method_exists($connectionError, 'getAttributes')) {
            $attributes = $connectionError->getAttributes();
            if (isset($attributes['message'])) {
                $error .= '<br />' . $attributes['message'];
            }
        }
        if ($name === 'debug_kit') {
            $error = 'Try adding your current <b>top level domain</b> to the
                <a href="https://book.cakephp.org/debugkit/5/en/index.html#configuration" target="_blank">DebugKit.safeTld</a>
            config and reload.';
            if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
                $error .= '<br />You need to install the PHP extension <code>pdo_sqlite</code> so DebugKit can work properly.';
            }
        }
    }

    return compact('connected', 'error');
};

if (!Configure::read('debug')) :
    throw new NotFoundException(
        'Please replace templates/Pages/home.php with your own version or re-enable debug mode.'
    );
endif;

?>
<!DOCTYPE html>
<html>
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        CakePHP: the rapid development PHP framework:
        <?= $this->fetch('title') ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->Html->css(['normalize.min', 'milligram.min', 'fonts', 'cake', 'home']) ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
</head>
<body>
    <header>
        <div class="container text-center">
            <a href="https://cakephp.org/" target="_blank" rel="noopener">
                <img alt="CakePHP" src="https://cakephp.org/v2/img/logos/CakePHP_Logo.svg" width="350" />
            </a>
            <h1>
                Welcome to CakePHP <?= h(Configure::version()) ?> Chiffon (🍰)
            </h1>
        </div>
    </header>
    <main class="main">
        <div class="container">
            <div class="content">
                <div class="row">
                    <div class="column">
                        <div class="message default text-center">
                            <small>Please be aware that this page will not be shown if you turn off debug mode unless you replace templates/Pages/home.php with your own version.</small>
                        </div>
                        <div id="url-rewriting-warning" style="padding: 1rem; background: #fcebea; color: #cc1f1a; border-color: #ef5753;">
                            <ul>
                                <li class="bullet problem">
                                    URL rewriting is not properly configured on your server.<br />
                                    1) <a target="_blank" rel="noopener" href="https://book.cakephp.org/5/en/installation.html#url-rewriting">Help me configure it</a><br />
                                    2) <a target="_blank" rel="noopener" href="https://book.cakephp.org/5/en/development/configuration.html#general-configuration">I don't / can't use URL rewriting</a>
                                </li>
                            </ul>
                        </div>
                        <?php Debugger::checkSecurityKeys(); ?>
                    </div>
                </div>
                <div class="row">
                    <div class="column">
                        <h4>Environment</h4>
                        <ul>
                        <?php if (version_compare(PHP_VERSION, '8.1.0', '>=')) : ?>
                            <li class="bullet success">Your version of PHP is 8.1.0 or higher (detected <?= PHP_VERSION ?>).</li>
                        <?php else : ?>
                            <li class="bullet problem">Your version of PHP is too low. You need PHP 8.1.0 or higher to use CakePHP (detected <?= PHP_VERSION ?>).</li>
                        <?php endif; ?>

                        <?php if (extension_loaded('mbstring')) : ?>
                            <li class="bullet success">Your version of PHP has the mbstring extension loaded.</li>
                        <?php else : ?>
                            <li class="bullet problem">Your version of PHP does NOT have the mbstring extension loaded.</li>
                        <?php endif; ?>

                        <?php if (extension_loaded('openssl')) : ?>
                            <li class="bullet success">Your version of PHP has the openssl extension loaded.</li>
                        <?php else : ?>
                            <li class="bullet problem">Your version of PHP does NOT have the openssl extension loaded.</li>
                        <?php endif; ?>

                        <?php if (extension_loaded('intl')) : ?>
                            <li class="bullet success">Your version of PHP has the intl extension loaded.</li>
                        <?php else : ?>
                            <li class="bullet problem">Your version of PHP does NOT have the intl extension loaded.</li>
                        <?php endif; ?>

                        <?php if (ini_get('zend.assertions') !== '1') : ?>
                            <li class="bullet problem">You should set <code>zend.assertions</code> to <code>1</code> in your <code>php.ini</code> for your development environment.</li>
                        <?php endif; ?>
                        </ul>
                    </div>
                    <div class="column">
                        <h4>Filesystem</h4>
                        <ul>
                        <?php if (is_writable(TMP)) : ?>
                            <li class="bullet success">Your tmp directory is writable.</li>
                        <?php else : ?>
                            <li class="bullet problem">Your tmp directory is NOT writable.</li>
                        <?php endif; ?>

                        <?php if (is_writable(LOGS)) : ?>
                            <li class="bullet success">Your logs directory is writable.</li>
                        <?php else : ?>
                            <li class="bullet problem">Your logs directory is NOT writable.</li>
                        <?php endif; ?>

                        <?php $settings = Cache::getConfig('_cake_translations_'); ?>
                        <?php if (!empty($settings)) : ?>
                            <li class="bullet success">The <em><?= h($settings['className']) ?></em> is being used for core caching. To change the config edit config/app.php</li>
                        <?php else : ?>
                            <li class="bullet problem">Your cache is NOT working. Please check the settings in config/app.php</li>
                        <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="column">
                        <h4>Database</h4>
                        <?php
                        $result = $checkConnection('default');
                        ?>
                        <ul>
                        <?php if ($result['connected']) : ?>
                            <li class="bullet success">CakePHP is able to connect to the database.</li>
                        <?php else : ?>
                            <li class="bullet problem">CakePHP is NOT able to connect to the database.<br /><?= h($result['error']) ?></li>
                        <?php endif; ?>
                        </ul>
                    </div>
                    <div class="column">
                        <h4>DebugKit</h4>
                        <ul>
                        <?php if (Plugin::isLoaded('DebugKit')) : ?>
                            <li class="bullet success">DebugKit is loaded.</li>
                            <?php
                            $result = $checkConnection('debug_kit');
                            ?>
                            <?php if ($result['connected']) : ?>
                                <li class="bullet success">DebugKit can connect to the database.</li>
                            <?php else : ?>
                                <li class="bullet problem">There are configuration problems present which need to be fixed:<br /><?= $result['error'] ?></li>
                            <?php endif; ?>
                        <?php else : ?>
                            <li class="bullet problem">DebugKit is <strong>not</strong> loaded.</li>
                        <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="column links">
                        <h3>Getting Started</h3>
                        <a target="_blank" rel="noopener" href="https://book.cakephp.org/5/en/">CakePHP Documentation</a>
                        <a target="_blank" rel="noopener" href="https://book.cakephp.org/5/en/tutorials-and-examples/cms/installation.html">The 20 min CMS Tutorial</a>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="column links">
                        <h3>Help and Bug Reports</h3>
                        <a target="_blank" rel="noopener" href="https://slack-invite.cakephp.org/">Slack</a>
                        <a target="_blank" rel="noopener" href="https://github.com/cakephp/cakephp/issues">CakePHP Issues</a>
                        <a target="_blank" rel="noopener" href="https://discourse.cakephp.org/">CakePHP Forum</a>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="column links">
                        <h3>Docs and Downloads</h3>
                        <a target="_blank" rel="noopener" href="https://api.cakephp.org/">CakePHP API</a>
                        <a target="_blank" rel="noopener" href="https://bakery.cakephp.org">The Bakery</a>
                        <a target="_blank" rel="noopener" href="https://book.cakephp.org/5/en/">CakePHP Documentation</a>
                        <a target="_blank" rel="noopener" href="https://plugins.cakephp.org">CakePHP plugins repo</a>
                        <a target="_blank" rel="noopener" href="https://github.com/cakephp/">CakePHP Code</a>
                        <a target="_blank" rel="noopener" href="https://github.com/FriendsOfCake/awesome-cakephp">CakePHP Awesome List</a>
                        <a target="_blank" rel="noopener" href="https://www.cakephp.org">CakePHP</a>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="column links">
                        <h3>Training and Certification</h3>
                        <a target="_blank" rel="noopener" href="https://cakefoundation.org/">Cake Software Foundation</a>
                        <a target="_blank" rel="noopener" href="https://training.cakephp.org/">CakePHP Training</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>


===== FILE: templates\Project\index.php =====

<?php
/**
 * @var \App\View\AppView $this
 * @var array<int,array{slug:string,title:string}> $items
 */
?>
<div class="content">
    <h1>Projektmateriale</h1>
    <p>Vælg et dokument nedenfor. Hvis filen mangler i webroot, får du en besked om hvor den skal placeres.</p>
    <ul>
        <?php foreach ($items as $item): ?>
            <li>
                <?= $this->Html->link(h($item['title']), ['action' => 'view', $item['slug']]) ?>
                &nbsp;·&nbsp;
                <?= $this->Html->link('Tekst', ['action' => 'text', $item['slug']]) ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <p>
        <?= $this->Html->link('Udfyld krav (demo)', ['controller' => 'Claims', 'action' => 'start'], ['class' => 'button']) ?>
        &nbsp;
        <?= $this->Html->link('Reimbursement form (demo)', ['controller' => 'Reimbursement', 'action' => 'start']) ?>
        &nbsp;
        <?= $this->Html->link('Start din sag (udbetaling nu)', ['controller' => 'ClientClaims', 'action' => 'start'], ['class' => 'button']) ?>
    </p>
</div>


===== FILE: templates\Project\text.php =====

<?php
/**
 * @var \App\View\AppView $this
 * @var string|null $textContent
 * @var bool $parserAvailable
 * @var string $title
 * @var string $base
 * @var string $slug
 */
?>
<div class="content">
    <p><?= $this->Html->link('← Tilbage', ['action' => 'view', $slug]) ?></p>
    <h1><?= h($title) ?> – Tekstudtræk</h1>

    <?php if ($textContent !== null && $textContent !== ''): ?>
        <pre style="white-space: pre-wrap; background:#fafafa; border:1px solid #eee; padding:1rem;"><?= h($textContent) ?></pre>
    <?php else: ?>
        <?php if (!$parserAvailable): ?>
            <div class="notice warning">
                <p>PDF-parser er ikke installeret. For at aktivere tekstudtræk kan du installere <code>smalot/pdfparser</code>.</p>
            </div>
        <?php else: ?>
            <div class="notice">
                <p>Kunne ikke udtrække tekst fra PDF’en. Filen kan stadig læses via visningssiden.</p>
            </div>
        <?php endif; ?>
        <p><?= $this->Html->link('Åbn dokument', ['action' => 'view', $slug], ['class' => 'button']) ?></p>
    <?php endif; ?>
</div>


===== FILE: templates\Project\view.php =====

<?php
/**
 * @var \App\View\AppView $this
 * @var array{fsPath:string,webPath:string}|null $fileInfo
 * @var string $slug
 * @var string $base
 */
?>
<div class="content">
    <p><?= $this->Html->link('← Tilbage', ['action' => 'index']) ?></p>
    <h1><?= h($title ?? 'Dokument') ?></h1>

    <?php if (!$fileInfo): ?>
        <div class="notice warning">
            <p>Filen for "<?= h($base) ?>" blev ikke fundet i webroot.</p>
            <p>Tilføj en fil i mappen <code>webroot/</code> (evt. under <code>webroot/files/</code> eller <code>webroot/docs/</code>) med et af følgende navne:</p>
            <ul>
                <li><code><?= h($base) ?>.pdf</code></li>
                <li><code><?= h($base) ?>.html</code> eller <code><?= h($base) ?>.htm</code></li>
                <li>Et billede: <code><?= h($base) ?>.png</code>, <code>.jpg</code>, <code>.svg</code> mv.</li>
            </ul>
            <p>Opdater siden efter upload.</p>
        </div>
    <?php else: ?>
        <?php
        $ext = strtolower(pathinfo($fileInfo['webPath'], PATHINFO_EXTENSION));
        $embeds = ['pdf','html','htm'];
        ?>
        <?php if (in_array($ext, $embeds, true)): ?>
            <?php if ($ext === 'pdf'): ?>
                <object data="<?= h($fileInfo['webPath']) ?>#view=fit" type="application/pdf" width="100%" height="800">
                    <p>Din browser kan ikke vise PDF. Du kan <a href="<?= h($fileInfo['webPath']) ?>" download>downloade filen</a>.</p>
                </object>
            <?php else: ?>
                <iframe src="<?= h($fileInfo['webPath']) ?>" width="100%" height="800" style="border:1px solid #ddd"></iframe>
            <?php endif; ?>
        <?php elseif (in_array($ext, ['png','jpg','jpeg','gif','webp','svg'], true)): ?>
            <figure>
                <img src="<?= h($fileInfo['webPath']) ?>" alt="<?= h($base) ?>" style="max-width:100%;height:auto" />
            </figure>
        <?php else: ?>
            <p>Filtypen (.<code><?= h($ext) ?></code>) vises ikke direkte her. Du kan hente den nedenfor.</p>
        <?php endif; ?>

        <p>
            <?= $this->Html->link('Download', $fileInfo['webPath'], ['download' => true, 'class' => 'button']) ?>
            &nbsp;
            <?= $this->Html->link('Åbn i ny fane', $fileInfo['webPath'], ['target' => '_blank', 'rel' => 'noopener']) ?>
        </p>
    <?php endif; ?>
</div>


===== FILE: templates\Reimbursement\start.php =====

<?php
/** @var \App\View\AppView $this */
?>
<div class="content">
  <h1>Reimbursement form (demo)</h1>
  <p>Udfyld felterne nedenfor for at generere en enkel PDF-opsummering. Vi kan senere udfylde den officielle PDF direkte.</p>
  <div style="margin:8px 0; display:flex; gap:8px; align-items:center;">
    <?php
      $files = glob(CONFIG . 'demo' . DIRECTORY_SEPARATOR . '*.json') ?: [];
      $fixtures = [];
      foreach ($files as $f) {
        $base = basename($f, '.json');
        // Human label from filename
        $label = ucwords(str_replace(['_', '-'], [' ', ' '], $base));
        $fixtures[$base] = $label;
      }
      if (empty($fixtures)) {
        $fixtures = ['ice_125m' => 'Ice 125m'];
      }
    ?>
    <label for="demo-case" style="margin:0;">Vælg eksempel:</label>
    <select id="demo-case">
      <?php foreach ($fixtures as $value => $label): ?>
        <option value="<?= h($value) ?>" <?= $value === 'ice_125m' ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="button" id="load-demo" class="button">Indlæs eksempel</button>
  </div>
  <?= $this->Form->create(null, ['url' => ['action' => 'generate']]) ?>
    <fieldset>
      <legend>Kontakt</legend>
      <?= $this->Form->control('name', ['label' => 'Navn']) ?>
      <?= $this->Form->control('email', ['label' => 'Email']) ?>
    </fieldset>
    <fieldset>
      <legend>Your journey details</legend>
      <?= $this->Form->control('operator', ['label' => 'Railway undertaking']) ?>
      <?= $this->Form->control('dep_date', ['label' => 'Departure date (dd/mm/yyyy)']) ?>
      <?= $this->Form->control('dep_station', ['label' => 'Departure station']) ?>
      <?= $this->Form->control('arr_station', ['label' => 'Destination station']) ?>
      <?= $this->Form->control('dep_time', ['label' => 'Scheduled departure (hh:mm)']) ?>
      <?= $this->Form->control('arr_time', ['label' => 'Scheduled arrival (hh:mm)']) ?>
      <?= $this->Form->control('train_no', ['label' => 'Train no./category']) ?>
      <?= $this->Form->control('ticket_no', ['label' => 'Ticket No(s)/Booking Ref']) ?>
      <?= $this->Form->control('price', ['label' => 'Ticket price(s)']) ?>
      <?= $this->Form->control('actual_arrival_date', ['label' => 'Date of actual arrival (dd/mm/yyyy)']) ?>
      <?= $this->Form->control('actual_dep_time', ['label' => 'Actual departure time (hh:mm)']) ?>
      <?= $this->Form->control('actual_arr_time', ['label' => 'Actual arrival time (hh:mm)']) ?>
      <?= $this->Form->control('missed_connection_station', ['label' => 'Missed connection in (station)']) ?>
    </fieldset>
    <fieldset>
      <legend>Reason for claim</legend>
      <?= $this->Form->control('reason_delay', ['label' => 'Delay', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('reason_cancellation', ['label' => 'Cancellation', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('reason_missed_conn', ['label' => 'Missed connection', 'type' => 'checkbox']) ?>
    </fieldset>
    <?= $this->Form->button('Generér PDF (opsummering)') ?>
  <?= $this->Form->end() ?>

  <?= $this->Form->create(null, ['url' => ['action' => 'official']]) ?>
    <?php /* Reuse same set of fields for official PDF; minimal subset repeated */ ?>
    <?= $this->Form->hidden('name', ['id' => 'off_name']) ?>
    <?= $this->Form->hidden('email', ['id' => 'off_email']) ?>
    <?= $this->Form->hidden('operator', ['id' => 'off_operator']) ?>
    <?= $this->Form->hidden('dep_date', ['id' => 'off_dep_date']) ?>
    <?= $this->Form->hidden('dep_station', ['id' => 'off_dep_station']) ?>
    <?= $this->Form->hidden('arr_station', ['id' => 'off_arr_station']) ?>
    <?= $this->Form->hidden('dep_time', ['id' => 'off_dep_time']) ?>
    <?= $this->Form->hidden('arr_time', ['id' => 'off_arr_time']) ?>
    <?= $this->Form->hidden('train_no', ['id' => 'off_train_no']) ?>
    <?= $this->Form->hidden('ticket_no', ['id' => 'off_ticket_no']) ?>
    <?= $this->Form->hidden('price', ['id' => 'off_price']) ?>
    <?= $this->Form->hidden('actual_arrival_date', ['id' => 'off_actual_arrival_date']) ?>
    <?= $this->Form->hidden('actual_dep_time', ['id' => 'off_actual_dep_time']) ?>
    <?= $this->Form->hidden('actual_arr_time', ['id' => 'off_actual_arr_time']) ?>
    <?= $this->Form->hidden('missed_connection_station', ['id' => 'off_missed_connection_station']) ?>
    <?= $this->Form->hidden('reason_delay', ['id' => 'off_reason_delay']) ?>
    <?= $this->Form->hidden('reason_cancellation', ['id' => 'off_reason_cancellation']) ?>
    <?= $this->Form->hidden('reason_missed_conn', ['id' => 'off_reason_missed_conn']) ?>
    <?= $this->Form->button('Udfyld officiel formular (FPDI)') ?>
  <?= $this->Form->end() ?>

    <script>
    (function(){
      // Keep hidden official fields in sync with visible inputs
      const forms = document.querySelectorAll('form');
      const dataForm = forms[0];
      function syncFieldByName(name){
        const src = dataForm ? dataForm.querySelector(`[name="${name}"]`) : null;
        const off = document.getElementById('off_' + name);
        if (!src || !off) return;
        if (src.type === 'checkbox') {
          off.value = src.checked ? '1' : '';
        } else {
          off.value = src.value || '';
        }
      }
      function syncAll(){
        if (!dataForm) return;
        const els = dataForm.querySelectorAll('input[name], select[name], textarea[name]');
        els.forEach(el => {
          if (!el.name) return;
          syncFieldByName(el.name);
        });
      }
      if (dataForm) {
        dataForm.addEventListener('input', function(e){
          const el = e.target;
          if (el && el.name) syncFieldByName(el.name);
        });
        dataForm.addEventListener('change', function(e){
          const el = e.target;
          if (el && el.name) syncFieldByName(el.name);
        });
        // Initial sync in case of prefilled values
        syncAll();
      }
      const btn = document.getElementById('load-demo');
      const sel = document.getElementById('demo-case');
      if (!btn) return;
      const demoUrl = <?= json_encode($this->Url->build('/api/demo/fixtures')) ?>; // respects base path (/rail_app)
      btn.addEventListener('click', async function(){
        try {
          btn.disabled = true; btn.textContent = 'Indlæser…';
          const caseName = sel ? sel.value : 'ice_125m';
          const res = await fetch(demoUrl + '?case=' + encodeURIComponent(caseName));
          if (!res.ok) {
            const text = await res.text();
            throw new Error('HTTP ' + res.status + ' ' + res.statusText + ' — ' + text.slice(0, 200));
          }
          let data;
          try { data = await res.json(); } catch(e) {
            const text = await res.text();
            throw new Error('Ugyldigt JSON-svar: ' + text.slice(0, 200));
          }
          const j = data.journey || {};
          const map = {
            name: 'John Doe',
            email: 'john@example.com',
            operator: j.operatorName?.value,
            dep_date: j.depDate?.value,
            dep_station: j.depStation?.value,
            arr_station: j.arrStation?.value,
            dep_time: j.schedDepTime?.value,
            arr_time: j.schedArrTime?.value,
            train_no: [j.trainNo?.value, j.trainCategory?.value].filter(Boolean).join(' '),
            ticket_no: j.bookingRef?.value || j.ticketNumber?.value,
            price: j.ticketPrice?.value,
            actual_arrival_date: j.actualArrDate?.value,
            actual_dep_time: j.actualDepTime?.value,
            actual_arr_time: j.actualArrTime?.value,
            missed_connection_station: j.missedConnectionAt?.value
          };
          for (const [k,v] of Object.entries(map)){
            const el = document.querySelector(`[name="${k}"]`);
            if (el && v) el.value = v;
            const off = document.getElementById('off_' + k);
            if (off && v) off.value = v;
          }
          // Sync reason checkboxes into official hidden inputs
          const reasons = ['reason_delay','reason_cancellation','reason_missed_conn'];
          for (const r of reasons) {
            const cb = document.querySelector(`[name="${r}"]`);
            const off = document.getElementById('off_' + r);
            if (cb && off) off.value = cb.checked ? '1' : '';
          }
          // Simple defaults per case (optional)
          if (sel && sel.value === 'ter_missed_conn') {
            const cb = document.querySelector('[name="reason_missed_conn"]');
            if (cb) cb.checked = true;
            const off = document.getElementById('off_reason_missed_conn');
            if (off) off.value = '1';
          }
          btn.textContent = 'Eksempel indlæst ✔';
          // Re-sync to ensure hidden fields reflect any programmatic changes
          syncAll();
        } catch(e) { console.error(e); }
        finally { btn.disabled = false; }
      });
    })();
    </script>

  <p>Se projektmateriale: <?= $this->Html->link('Flow charts', ['controller' => 'Project', 'action' => 'index']) ?></p>
</div>


===== FILE: templates\Upload\index.php =====

<?php
/** @var \App\View\AppView $this */
?>
<div class="content">
  <h1>Upload din billet</h1>
  <p>Tag et foto, upload et screenshot eller PKPass. Vi tjekker først Art. 12 (gennemgående billet).</p>
  <?= $this->Form->create(null, ['url' => ['action' => 'analyze'], 'type' => 'file']) ?>
    <fieldset>
      <legend>Billet</legend>
      <?= $this->Form->control('ticket', ['type' => 'file', 'label' => 'Billede/PDF/PKPass']) ?>
      <?= $this->Form->control('country', ['label' => 'Land (hint, hvis parsing ikke er klar endnu)', 'value' => 'FR']) ?>
    </fieldset>
    <details style="margin:10px 0;">
      <summary>Udvikler-genvej: Indsæt Journey JSON (overrider parsing)</summary>
      <textarea name="journey" style="width:100%;height:140px;" placeholder='{"segments":[{"country":"FR"}],"is_international_inside_eu":false,"is_international_beyond_eu":false,"is_long_domestic":false}'></textarea>
      <p style="font-size:12px;color:#666;">Hvis angivet, bruges denne direkte til Art. 12/Exemptions.</p>
    </details>
    <?= $this->Form->button('Analyser og tjek Art. 12') ?>
  <?= $this->Form->end() ?>

  <p style="margin-top:14px;">Se også: <?= $this->Html->link('Flow chart (v4)', ['/project/flowchart']) ?> og <?= $this->Html->link('Forklaring', ['/project/forklaring']) ?>.</p>
</div>


===== FILE: templates\Upload\result.php =====

<?php
/** @var \App\View\AppView $this */
/** @var array $profile */
/** @var bool $art12_applies */
/** @var array $art12 */
/** @var array $art9 */
/** @var array $refund */
/** @var array $refusion */
/** @var array $claim */
/** @var string|null $savedPath */
/** @var string[] $errors */
?>
<div class="content">
  <h1>Resultat: Art. 12</h1>
  <?php if (!empty($errors)): ?>
    <div class="message error">
      <ul><?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <p><strong>Gennemgående billet regler (Art. 12):</strong>
    <?php if ($art12_applies): ?>
      <span style="color:green;">GÆLDER</span>
    <?php else: ?>
      <span style="color:#b00;">UNDTAGET</span>
    <?php endif; ?>
  </p>

  <?php if ($savedPath): ?>
    <p>Upload gemt: <?= h(basename($savedPath)) ?></p>
  <?php endif; ?>

  <h3>Step status</h3>
  <ul>
    <li>Art. 12 (gennemgående): <?= h($art12['art12_applies'] === true ? '✓' : ($art12['art12_applies'] === false ? '✗' : '?')) ?></li>
    <li>Art. 9 (information): <?= h($art9['art9_ok'] === true ? '✓' : ($art9['art9_ok'] === false ? '✗' : '?')) ?></li>
    <li>Refund (Art. 18): <?= h(($refund['eligible'] ?? null) === true ? '✓' : (($refund['eligible'] ?? null) === false ? '✗' : '?')) ?></li>
    <li>Refusion (Art. 18): <?= h($refusion['outcome'] ?? '-') ?></li>
    <li>Kompensation (Art. 19): <?= h(($claim['breakdown']['compensation']['eligible'] ?? false) ? '✓' : '✗') ?> (<?= h(($claim['breakdown']['compensation']['pct'] ?? 0) . '%') ?>)</li>
    <li>Udgifter (Art. 20): <?= h(number_format((float)($claim['breakdown']['expenses']['total'] ?? 0), 2)) ?> <?= h($claim['totals']['currency'] ?? 'EUR') ?></li>
    <li>Samlet brutto: <?= h(number_format((float)($claim['totals']['gross_claim'] ?? 0), 2)) ?> <?= h($claim['totals']['currency'] ?? 'EUR') ?></li>
    <li>Servicefee 25%: <?= h(number_format((float)($claim['totals']['service_fee_amount'] ?? 0), 2)) ?> <?= h($claim['totals']['currency'] ?? 'EUR') ?></li>
    <li>Netto til klient: <strong><?= h(number_format((float)($claim['totals']['net_to_client'] ?? 0), 2)) ?> <?= h($claim['totals']['currency'] ?? 'EUR') ?></strong></li>
  </ul>

  <h3>Detaljeret profil</h3>
  <pre style="background:#f8f8f8;border:1px solid #eee;padding:10px;white-space:pre-wrap;"><?= h(json_encode($profile, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>

  <h3>Claim breakdown</h3>
  <pre style="background:#f8f8f8;border:1px solid #eee;padding:10px;white-space:pre-wrap;"><?= h(json_encode($claim, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>

  <p><?= $this->Html->link('Tilbage til upload', ['action' => 'index']) ?></p>
</div>


