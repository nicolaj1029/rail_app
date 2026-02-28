<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Normalize country inputs to ISO-3166-1 alpha-2 codes (e.g., "Denmark" -> "DK").
 *
 * This is used to keep split-flow inputs consistent across:
 * - exemption matrix (country codes)
 * - NationalPolicy (TRIN 4/6)
 * - station datasets (country code filters)
 */
final class CountryNormalizer
{
    /** @var array<string,string>|null */
    private ?array $nameToIso2 = null;

    private function key(string $s): string
    {
        $t = trim($s);
        // Allow DK - Denmark / Denmark (DK) / etc
        $t = preg_replace('/\([^)]*\)/u', ' ', $t) ?? $t;
        $t = preg_replace('/[^\\p{L}]+/u', ' ', $t) ?? $t;
        $t = trim($t);
        // Transliterate to ASCII for robust matching (e.g., "Danmark")
        try {
            $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
            if ($conv !== false) { $t = (string)$conv; }
        } catch (\Throwable $e) { /* ignore */ }
        $t = strtolower($t);
        return preg_replace('/\\s+/', '', $t) ?? $t;
    }

    private function buildMap(): array
    {
        $map = [];
        // Base country name map (English) - do not rely on framework constants being defined.
        $base = [
            'AT'=>'Austria','BE'=>'Belgium','BG'=>'Bulgaria','CZ'=>'Czechia','DE'=>'Germany','DK'=>'Denmark','EE'=>'Estonia',
            'ES'=>'Spain','FI'=>'Finland','FR'=>'France','GR'=>'Greece','HR'=>'Croatia','HU'=>'Hungary','IE'=>'Ireland','IT'=>'Italy',
            'LT'=>'Lithuania','LU'=>'Luxembourg','LV'=>'Latvia','NL'=>'Netherlands','PL'=>'Poland','PT'=>'Portugal','RO'=>'Romania',
            'SE'=>'Sweden','SI'=>'Slovenia','SK'=>'Slovakia','CH'=>'Switzerland','GB'=>'United Kingdom','NO'=>'Norway',
        ];
        foreach ($base as $cc => $name) {
            $map[$this->key((string)$name)] = (string)$cc;
        }

        // If framework constants are available, extend with OperatorCatalog (includes any future additions).
        try {
            $cat = new OperatorCatalog();
            foreach ($cat->getCountries() as $cc => $name) {
                $cc2 = strtoupper((string)$cc);
                if (!preg_match('/^[A-Z]{2}$/', $cc2)) { continue; }
                $map[$this->key((string)$name)] = $cc2;
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Common local-language aliases / frequent UI inputs
        $map['danmark'] = 'DK';
        $map['denmark'] = 'DK';
        $map['deutschland'] = 'DE';
        $map['germany'] = 'DE';
        $map['sverige'] = 'SE';
        $map['sweden'] = 'SE';
        $map['norge'] = 'NO';
        $map['norway'] = 'NO';
        $map['suomi'] = 'FI';
        $map['finland'] = 'FI';
        $map['cesko'] = 'CZ';
        $map['czechia'] = 'CZ';
        $map['czechrepublic'] = 'CZ';

        return $map;
    }

    /**
     * @return string ISO2 code (e.g., "DK") or empty string if unknown.
     */
    public function toIso2(?string $countryRaw): string
    {
        $raw = strtoupper(trim((string)$countryRaw));
        if ($raw === '') { return ''; }

        // If it's already ISO2
        if (preg_match('/^[A-Z]{2}$/', $raw)) { return $raw; }

        // If it starts with a code (e.g. "DK - Denmark")
        if (preg_match('/^([A-Z]{2})\\b/', $raw, $m)) { return (string)$m[1]; }

        // Strip non-letters and re-check
        $alpha = strtoupper(preg_replace('/[^A-Z]/i', '', $raw) ?? '');
        if (preg_match('/^[A-Z]{2}$/', $alpha)) { return $alpha; }

        if ($this->nameToIso2 === null) {
            $this->nameToIso2 = $this->buildMap();
        }

        $k = $this->key((string)$countryRaw);
        $iso = $this->nameToIso2[$k] ?? '';
        return $iso;
    }
}
