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
