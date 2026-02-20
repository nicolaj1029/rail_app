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

        $payload = [
            'journey' => $fixture['journey'] ?? [],
            'wizard' => $fixture['wizard'] ?? [],
            'computeOverrides' => $fixture['computeOverrides'] ?? [],
        ];
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
}
