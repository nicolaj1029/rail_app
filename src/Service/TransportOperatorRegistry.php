<?php
declare(strict_types=1);

namespace App\Service;

final class TransportOperatorRegistry
{
    /** @var array<int,array<string,mixed>> */
    private array $operators = [];

    public function __construct(?string $path = null)
    {
        $path = $path ?? (CONFIG . 'data' . DIRECTORY_SEPARATOR . 'transport_operator_registry.json');
        if (!is_file($path)) {
            return;
        }

        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) {
            return;
        }

        $this->operators = array_values((array)($data['operators'] ?? []));
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByName(string $mode, string $text): ?array
    {
        $needle = trim($text);
        if ($needle === '') {
            return null;
        }

        $mode = strtolower(trim($mode));
        foreach ($this->operators as $operator) {
            if (strtolower((string)($operator['mode'] ?? '')) !== $mode) {
                continue;
            }
            $names = array_unique(array_merge(
                [(string)($operator['name'] ?? '')],
                array_map('strval', (array)($operator['aliases'] ?? []))
            ));
            foreach ($names as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }
                $pattern = '/(?<![A-Za-z0-9])' . preg_quote($name, '/') . '(?![A-Za-z0-9])/iu';
                if (preg_match($pattern, $needle)) {
                    return $operator;
                }
            }
        }

        return null;
    }

    public function deriveCountryCode(string $mode, string $text): ?string
    {
        $match = $this->findByName($mode, $text);
        $country = strtoupper(trim((string)($match['country_code'] ?? '')));

        return $country !== '' ? $country : null;
    }

    public function deriveEuFlag(string $mode, string $text): ?bool
    {
        $match = $this->findByName($mode, $text);
        if ($match === null || !array_key_exists('is_eu_operator', $match)) {
            return null;
        }

        return (bool)$match['is_eu_operator'];
    }
}
