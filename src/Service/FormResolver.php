<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Resolves which form to generate (EU standard vs national) and locates the template path if national.
 * Decision inputs: country code (ISO2), optional operator/product context.
 * Data source: config/pdf/national_forms.json
 */
class FormResolver
{
    /**
     * Decide best form for given journey context.
     *
     * @param array{country?:string, operator?:string, product?:string} $ctx
     * @return array{form:string, reason:string, national?:array{country:string, path?:string}} form is one of: eu_standard_claim | national_claim | none
     */
    public function decide(array $ctx): array
    {
        $country = strtoupper((string)($ctx['country'] ?? ''));
        $operator = (string)($ctx['operator'] ?? '');
        $product = (string)($ctx['product'] ?? '');
        $map = $this->loadMap();
        $countries = (array)($map['countries'] ?? []);
        $def = (array)($map['defaults'] ?? ['prefer_national' => false]);

        if ($country === '') {
            return [
                'form' => 'eu_standard_claim',
                'reason' => 'No country provided; defaulting to EU form.'
            ];
        }

        $entry = $countries[$country] ?? null;
        $preferNat = (bool)($entry['prefer_national'] ?? $def['prefer_national'] ?? false);
        $fileList = (array)($entry['filenames'] ?? $def['filenames'] ?? []);
        // If matrix says use EU (not preferred national), return EU
        if (!$preferNat) {
            return [
                'form' => 'eu_standard_claim',
                'reason' => sprintf('%s marked EU-level; EU standard applies.', $country)
            ];
        }

        // If prefer national, ensure we actually have a template present
        $files = $fileList;
        $found = null;
        foreach ($files as $rel) {
            $p = WWW_ROOT . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
            if (is_file($p)) { $found = $p; break; }
        }
        if ($found) {
            return [
                'form' => 'national_claim',
                'reason' => sprintf('%s prefers national; template found.', $country),
                'national' => ['country' => $country, 'path' => $found]
            ];
        }
        // Prefer national but not available → fall back to EU
        return [
            'form' => 'eu_standard_claim',
            'reason' => sprintf('%s prefers national; no local template found, fallback EU.', $country),
            'national' => ['country' => $country]
        ];
    }

    /**
     * Load national forms matrix from config/pdf/national_forms.json
     * @return array<string,mixed>
     */
    public function loadMap(): array
    {
        $path = CONFIG . 'pdf' . DIRECTORY_SEPARATOR . 'national_forms.json';
        if (!is_file($path)) { return []; }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
