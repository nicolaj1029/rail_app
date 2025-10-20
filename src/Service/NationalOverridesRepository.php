<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Loads national/operator overrides for compensation thresholds from config/data/national_overrides.json
 */
class NationalOverridesRepository
{
    private string $path;
    /** @var array<int,array<string,mixed>> */
    private array $items = [];
    /** @var array<string,string> */
    private array $countryCodeMap = [
        'DK' => 'Denmark', 'DE' => 'Germany', 'FR' => 'France', 'ES' => 'Spain', 'SE' => 'Sweden',
        'IT' => 'Italy', 'NL' => 'Netherlands', 'BE' => 'Belgium', 'AT' => 'Austria', 'CH' => 'Switzerland',
        'NO' => 'Norway', 'FI' => 'Finland', 'PL' => 'Poland', 'CZ' => 'Czech Republic', 'SK' => 'Slovakia',
        'HU' => 'Hungary', 'RO' => 'Romania', 'BG' => 'Bulgaria', 'IE' => 'Ireland', 'GB' => 'United Kingdom'
    ];

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'national_overrides.json';
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
     * Return all override records loaded from JSON.
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Find best matching override by operator/product.
     * @param array{country?:string, operator?:string, product?:string} $query
     * @return array<string,mixed>|null
     */
    public function findOne(array $query): ?array
    {
        // If no identifying attributes are provided, do not return any override.
        $hasQuery = !empty($query['operator']) || !empty($query['product']) || !empty($query['country']);
        if (!$hasQuery) {
            return null;
        }

        $countryForms = [];
        if (!empty($query['country'])) {
            $q = (string)$query['country'];
            $countryForms[] = strtolower($q);
            $qTrim = strtoupper(preg_replace('/[^A-Z]/i', '', $q) ?? '');
            if (isset($this->countryCodeMap[$qTrim])) {
                $countryForms[] = strtolower($this->countryCodeMap[$qTrim]);
            }
        }

        $best = null;
        $bestScore = -1;
        foreach ($this->items as $row) {
            $score = 0;
            if (!empty($query['operator']) && isset($row['operator']) && strcasecmp((string)$row['operator'], (string)$query['operator']) === 0) {
                $score += 2; // operator match is strong
            }
            if (!empty($query['product']) && isset($row['product']) && strcasecmp((string)$row['product'], (string)$query['product']) === 0) {
                $score += 2; // product match is strong
            }
            if (!empty($countryForms) && isset($row['country'])) {
                $rowCountry = strtolower((string)$row['country']);
                if (in_array($rowCountry, $countryForms, true)) {
                    $score += 1; // country match is weaker than operator/product
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        // Require at least one attribute to match
        return $bestScore > 0 ? $best : null;
    }
}
