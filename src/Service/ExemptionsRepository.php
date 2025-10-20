<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Loads exemption rules (articles exempted by country/scope) from config/data/exemptions.json
 */
class ExemptionsRepository
{
    private string $path;
    /** @var array<int,array<string,mixed>> */
    private array $items = [];

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'exemptions.json';
        $this->load();
    }

    private function load(): void
    {
        if (!is_file($this->path)) {
            $this->items = [];
            return;
        }
        $json = (string)file_get_contents($this->path);
        $data = json_decode($json, true);
        $this->items = is_array($data) ? $data : [];
    }

    /**
     * Find exemptions matching given parameters.
     * @param array{country?:string, scope?:string, internationalBeyondEU?:bool, serviceType?:string} $query
     * @return array<int,array<string,mixed>>
     */
    public function find(array $query): array
    {
        $country = isset($query['country']) ? strtolower($query['country']) : null;
        $scope = $query['scope'] ?? $query['serviceType'] ?? null;
        $intl = (bool)($query['internationalBeyondEU'] ?? false);
        $today = date('Y-m-d');
        $res = [];
        foreach ($this->items as $row) {
            if (!empty($row['until']) && $row['until'] < $today) {
                continue;
            }
            if ($country && isset($row['country']) && strtolower((string)$row['country']) !== $country) {
                continue;
            }
            if ($intl && !empty($row['internationalOnly']) && !$row['internationalOnly']) {
                // ok – row applies to both; keep
            }
            if ($scope && isset($row['scope']) && (string)$row['scope'] !== $scope) {
                continue;
            }
            $res[] = $row;
        }
        return $res;
    }
}
