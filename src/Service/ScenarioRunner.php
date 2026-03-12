<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Client;
use Cake\Utility\Hash;

/**
 * Lightweight runner that posts fixture payloads to the unified pipeline and compares expected vs actual.
 */
class ScenarioRunner
{
    private Client $http;
    private string $pipelineUrl;

    public function __construct(?Client $client = null, ?string $pipelineUrl = null)
    {
        $this->http = $client ?: new Client(['timeout' => 15]);
        $this->pipelineUrl = $pipelineUrl ?: (env('PIPELINE_RUN_URL') ?: 'http://localhost/rail_app/api/pipeline/run');
    }

    /**
     * Run a single fixture end-to-end.
     *
     * @return array{actual:array,expected:array,match:bool,diff:array}
     */
    public function evaluateFixture(array $fixture): array
    {
        if (!isset($fixture['version']) || (int)$fixture['version'] < 2) {
            $fixture = $this->migrateV1toV2($fixture);
        }
        $fixture = $this->normalizeStep34WizardKeys($fixture);

        $payload = [
            'journey' => $fixture['journey'] ?? [],
            'wizard' => $fixture['wizard'] ?? [],
            'computeOverrides' => $fixture['computeOverrides'] ?? [],
        ];
        foreach (['transport_mode', 'contract_meta', 'scope_meta', 'incident_meta'] as $key) {
            if (array_key_exists($key, $fixture)) {
                $payload[$key] = $fixture[$key];
            }
        }
        if (!empty($fixture['meta']) && is_array($fixture['meta'])) {
            $payload['meta'] = (array)$fixture['meta'];
        }

        // Pass Art.12 meta if present (added by enriched fixture mapper)
        if (!empty($fixture['art12_meta']) && is_array($fixture['art12_meta'])) {
            $payload['art12_meta'] = (array)$fixture['art12_meta'];
        }

        // Pass Art.9 meta if present to enable Article 9 evaluation in scenarios
        if (!empty($fixture['art9_meta']) && is_array($fixture['art9_meta'])) {
            $payload['art9_meta'] = (array)$fixture['art9_meta'];
        }

        $resp = $this->http->post($this->pipelineUrl, json_encode($payload), ['type' => 'json']);
        if (!$resp->isOk()) {
            return [
                'actual' => ['error' => 'pipeline_failed', 'status' => $resp->getStatusCode()],
                'expected' => $fixture['expected'] ?? [],
                'match' => false,
                'diff' => ['pipeline_error' => $resp->getJson() ?: $resp->getStringBody()],
            ];
        }

        $actual = (array)$resp->getJson();
        $actual = $this->enrichMultimodalActual($fixture, $actual);
        $expected = (array)($fixture['expected'] ?? []);
        $diff = $this->compareResults($expected, $actual);

        return [
            'actual' => $actual,
            'expected' => $expected,
            'match' => empty($diff),
            'diff' => $diff,
        ];
    }

    /**
     * Normalize the split-flow step 3/4 wizard keys so older fixtures keep working.
     *
     * New: step3_station + step4_journey
     * Legacy: step3_journey + step4_station
     */
    private function normalizeStep34WizardKeys(array $fixture): array
    {
        $fixture['wizard'] = (array)($fixture['wizard'] ?? []);
        $w = (array)$fixture['wizard'];

        if (!array_key_exists('step3_station', $w)) {
            $w['step3_station'] = (array)($w['step4_station'] ?? []);
        }
        if (!array_key_exists('step4_journey', $w)) {
            $w['step4_journey'] = (array)($w['step3_journey'] ?? ($w['step3_entitlements'] ?? []));
        }

        // Default journey info helpers (used by multiple evaluators); keep values if already present.
        $w['step4_journey'] = (array)($w['step4_journey'] ?? []);
        $w['step4_journey'] += [
            'preinformed_disruption' => $w['step4_journey']['preinformed_disruption'] ?? 'Ved ikke',
            'preinfo_channel' => $w['step4_journey']['preinfo_channel'] ?? 'Ved ikke',
            'realtime_info_seen' => $w['step4_journey']['realtime_info_seen'] ?? [],
        ];

        $fixture['wizard'] = $w;
        return $fixture;
    }

    /**
     * Simple deep compare with optional reasonContains helper.
     *
     * @param array $expected
     * @param array $actual
     * @return array<string,array<string,mixed>>
     */
    private function compareResults(array $expected, array $actual): array
    {
        $diff = [];
        foreach ($expected as $key => $expVal) {
            $actVal = Hash::get($actual, $key);
            if (is_array($expVal) && array_key_exists('reasonContains', $expVal)) {
                $needle = (string)$expVal['reasonContains'];
                if (!is_string($actVal) || strpos($actVal, $needle) === false) {
                    $diff[$key] = ['expected_reasonContains' => $needle, 'actual' => $actVal];
                }
                continue;
            }
            if ($expVal !== $actVal) {
                $diff[$key] = ['expected' => $expVal, 'actual' => $actVal];
            }
        }
        return $diff;
    }

    /**
     * Minimal V1 -> V2 migration so old fixtures do not break the runner.
     */
    private function migrateV1toV2(array $fixture): array
    {
        $fixture['version'] = 2;
        $fixture['wizard'] = (array)($fixture['wizard'] ?? []);
        $fixture['transport_mode'] = $fixture['transport_mode'] ?? 'rail';
        $legacyStep3 = (array)($fixture['wizard']['step3_entitlements'] ?? []);
        $fixture['wizard']['step3_journey'] = (array)($fixture['wizard']['step3_journey'] ?? $legacyStep3);
        $fixture['wizard']['step3_journey'] += [
            'preinformed_disruption' => $fixture['wizard']['step3_journey']['preinformed_disruption'] ?? 'Ved ikke',
            'preinfo_channel' => $fixture['wizard']['step3_journey']['preinfo_channel'] ?? 'Ved ikke',
            'realtime_info_seen' => $fixture['wizard']['step3_journey']['realtime_info_seen'] ?? [],
        ];
        if (!empty($fixture['wizard']['step4_choices']) && empty($fixture['wizard']['step5_choices'])) {
            $fixture['wizard']['step5_choices'] = (array)$fixture['wizard']['step4_choices'];
        }
        if (!empty($fixture['wizard']['step5_assistance']) && empty($fixture['wizard']['step6_assistance'])) {
            $fixture['wizard']['step6_assistance'] = (array)$fixture['wizard']['step5_assistance'];
        }
        $fixture['computeOverrides'] = (array)($fixture['computeOverrides'] ?? []);
        return $fixture;
    }

    /**
     * @param array<string,mixed> $fixture
     * @param array<string,mixed> $actual
     * @return array<string,mixed>
     */
    private function enrichMultimodalActual(array $fixture, array $actual): array
    {
        $transportMode = strtolower(trim((string)($fixture['transport_mode'] ?? 'rail')));
        $actual['transport_mode'] = $transportMode;
        if (!empty($fixture['contract_meta']) && is_array($fixture['contract_meta'])) {
            $actual['contract_meta'] = (array)$fixture['contract_meta'];
        }
        if (!empty($fixture['incident_meta']) && is_array($fixture['incident_meta'])) {
            $actual['incident_meta'] = (array)$fixture['incident_meta'];
        }

        if ($transportMode === 'ferry') {
            $scopeResolver = new FerryScopeResolver();
            $actual['ferry_scope'] = $scopeResolver->evaluate((array)($fixture['scope_meta'] ?? []));
            $contractResolver = new FerryContractResolver();
            $actual['ferry_contract'] = $contractResolver->evaluate((array)($fixture['contract_meta'] ?? []), (array)$actual['ferry_scope']);
            $rightsResolver = new FerryRightsEvaluator();
            $actual['ferry_rights'] = $rightsResolver->evaluate(
                (array)($fixture['incident_meta'] ?? []),
                (array)$actual['ferry_scope'],
                (array)$actual['ferry_contract']
            );
        } elseif (!empty($fixture['scope_meta']) && is_array($fixture['scope_meta'])) {
            $actual['scope_meta'] = (array)$fixture['scope_meta'];
        }

        return $actual;
    }
}
