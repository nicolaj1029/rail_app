<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Reads config/data/exemption_matrix.json and exposes lookups by country/scope.
 */
class ExemptionMatrixRepository
{
    private string $path;
    /** @var array<string,array<int,array<string,mixed>>> keyed by ISO country code */
    private array $matrix = [];
    /** @var array<string,string> */
    private array $nameToIso = [
        'austria' => 'AT', 'bulgaria' => 'BG', 'france' => 'FR', 'hungary' => 'HU', 'luxembourg' => 'LU',
        'poland' => 'PL', 'portugal' => 'PT', 'romania' => 'RO', 'slovakia' => 'SK', 'finland' => 'FI',
        'germany' => 'DE', 'sweden' => 'SE', 'czech republic' => 'CZ', 'croatia' => 'HR', 'latvia' => 'LV',
        'spain' => 'ES', 'italy' => 'IT', 'netherlands' => 'NL', 'belgium' => 'BE', 'denmark' => 'DK',
        'ireland' => 'IE', 'slovenia' => 'SI', 'estonia' => 'EE', 'lithuania' => 'LT', 'malta' => 'MT',
        'cz' => 'CZ', 'at' => 'AT', 'bg' => 'BG', 'fr' => 'FR', 'hu' => 'HU', 'lu' => 'LU', 'pl' => 'PL',
        'pt' => 'PT', 'ro' => 'RO', 'sk' => 'SK', 'fi' => 'FI', 'de' => 'DE', 'se' => 'SE', 'hr' => 'HR',
        'lv' => 'LV', 'es' => 'ES', 'it' => 'IT', 'nl' => 'NL', 'be' => 'BE', 'dk' => 'DK', 'ie' => 'IE',
        'si' => 'SI', 'ee' => 'EE', 'lt' => 'LT', 'mt' => 'MT',
    ];

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'exemption_matrix.json';
        $this->load();
    }

    private function load(): void
    {
        if (!is_file($this->path)) {
            $this->matrix = [];
            return;
        }
        $json = (string)file_get_contents($this->path);
        $data = json_decode($json, true);
        $this->matrix = is_array($data) ? $data : [];
    }

    /**
     * Find rows by country and optional scope.
     * @param array{country?:string, scope?:string} $query
     * @return array<int,array<string,mixed>>
     */
    public function find(array $query): array
    {
        $code = $this->toIso($query['country'] ?? null);
        if (!$code) { return []; }
        $rows = $this->matrix[$code] ?? [];
        $scope = $query['scope'] ?? null;
        if ($scope) {
            $rows = array_values(array_filter($rows, fn($r) => isset($r['scope']) && (string)$r['scope'] === (string)$scope));
        }
        return $rows;
    }

    private function toIso(?string $country): ?string
    {
        if (!$country) return null;
        $k = strtolower(trim($country));
        // direct ISO code
        if (strlen($k) <= 3 && isset($this->nameToIso[$k])) { return strtoupper($this->nameToIso[$k]); }
        // name mapping
        return $this->nameToIso[$k] ?? null;
    }
}
