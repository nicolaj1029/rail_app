<?php
declare(strict_types=1);

namespace App\Service;

final class SeasonPolicyCatalog
{
    /** @var array<string,mixed> */
    private array $data = [];

    public function __construct(?string $path = null)
    {
        $path = $path ?? (CONFIG . 'data' . DIRECTORY_SEPARATOR . 'season_policy_matrix.json');
        if (is_file($path)) {
            $decoded = json_decode((string)file_get_contents($path), true);
            if (is_array($decoded)) {
                $this->data = $decoded;
            }
        }
        if (empty($this->data)) {
            $this->data = ['schema_version' => 1, 'policies' => []];
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $policies = $this->data['policies'] ?? [];
        return is_array($policies) ? array_values($policies) : [];
    }

    /** @return string[] */
    public function eu27(): array
    {
        return [
            'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE',
        ];
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     *   Map of country => policy entries
     */
    public function byCountry(): array
    {
        $out = [];
        foreach ($this->all() as $p) {
            if (!is_array($p)) {
                continue;
            }
            $cc = strtoupper((string)($p['country'] ?? ''));
            if ($cc === '') {
                continue;
            }
            $out[$cc][] = $p;
        }
        ksort($out);
        return $out;
    }

    /**
     * Best-effort lookup for a season-pass policy.
     *
     * @return array<string,mixed>|null
     */
    public function find(string $operator, string $country = ''): ?array
    {
        $opIn = trim((string)$operator);
        $ccIn = strtoupper(trim((string)$country));
        if ($opIn === '' && $ccIn === '') {
            return null;
        }

        $normOp = self::norm($opIn);
        foreach ($this->all() as $p) {
            if (!is_array($p)) {
                continue;
            }
            $cc = strtoupper((string)($p['country'] ?? ''));
            if ($ccIn !== '' && $cc !== '' && $cc !== $ccIn) {
                continue;
            }

            $names = [(string)($p['operator'] ?? '')];
            $aliases = $p['aliases'] ?? [];
            if (is_array($aliases)) {
                foreach ($aliases as $a) {
                    $names[] = (string)$a;
                }
            }
            foreach ($names as $n) {
                if ($n === '') {
                    continue;
                }
                if (self::norm($n) === $normOp) {
                    return $p;
                }
            }
        }

        // Fallback: try mapping via OperatorCatalog canonicalization
        try {
            $cat = new OperatorCatalog();
            $found = $cat->findOperator($opIn, 'rail');
            if ($found && !empty($found['name'])) {
                $canon = (string)$found['name'];
                if (self::norm($canon) !== $normOp) {
                    return $this->find($canon, $ccIn);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    /**
     * Coverage report to drive backlog work.
     *
     * @return array<string,array<string,mixed>>
     *   country => {operators_total:int, season_policies:int, verified:int, with_links:int, missing_ops:string[]}
     */
    public function coverageReport(?OperatorCatalog $operatorCatalog = null, bool $eu27Only = true): array
    {
        $operatorCatalog = $operatorCatalog ?: new OperatorCatalog();
        $opsByCountry = (array)$operatorCatalog->getOperators('rail');
        $polByCountry = $this->byCountry();

        $countries = $eu27Only ? $this->eu27() : array_values(array_unique(array_merge(array_keys($opsByCountry), array_keys($polByCountry))));
        $out = [];

        foreach ($countries as $cc) {
            $cc = strtoupper((string)$cc);
            $ops = isset($opsByCountry[$cc]) && is_array($opsByCountry[$cc]) ? array_keys((array)$opsByCountry[$cc]) : [];
            $pols = isset($polByCountry[$cc]) && is_array($polByCountry[$cc]) ? (array)$polByCountry[$cc] : [];
            $present = [];
            $verifiedCount = 0;
            $withLinksCount = 0;
            foreach ($pols as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $opName = trim((string)($p['operator'] ?? ''));
                if ($opName !== '') {
                    $present[self::norm($opName)] = true;
                }
                if (!empty($p['verified'])) { $verifiedCount++; }
                $src = trim((string)($p['source_url'] ?? ''));
                $ch = (array)($p['claim_channel'] ?? []);
                $chUrl = trim((string)($ch['value'] ?? ''));
                if ($src !== '' || $chUrl !== '') { $withLinksCount++; }
                $aliases = $p['aliases'] ?? [];
                if (is_array($aliases)) {
                    foreach ($aliases as $a) {
                        $a = trim((string)$a);
                        if ($a !== '') {
                            $present[self::norm($a)] = true;
                        }
                    }
                }
            }

            $missing = [];
            foreach ($ops as $op) {
                if (!isset($present[self::norm((string)$op)])) {
                    $missing[] = (string)$op;
                }
            }
            sort($missing, SORT_NATURAL | SORT_FLAG_CASE);

            $out[$cc] = [
                'operators_total' => count($ops),
                'season_policies' => count($pols),
                'verified' => $verifiedCount,
                'with_links' => $withLinksCount,
                'missing_ops' => $missing,
            ];
        }
        ksort($out);

        return $out;
    }

    private static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = preg_replace('/[^a-z0-9 ]/u', '', $s) ?? $s;
        return trim((string)$s);
    }
}
