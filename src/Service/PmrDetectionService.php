<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Detects PMR/handicap status from ticket text and optional structured fields.
 * Outputs simple booleans for pmr_user and pmr_booked, plus evidence and confidence.
 */
class PmrDetectionService
{
    /** @return array{pmr_user:bool, pmr_booked:bool, discount_type:?string, evidence:array<int,string>, confidence:float} */
    public function analyze(array $input): array
    {
        $rawText = (string)($input['rawText'] ?? '');
        // Normalize whitespace to improve regex matching
        $text = preg_replace('/\s+/u', ' ', (string)$rawText) ?? (string)$rawText;
        $textLow = mb_strtolower($text, 'UTF-8');
        $evidence = [];
        $bookedHits = 0; $discountHits = 0; $iconHits = 0;

        // Assistance booking patterns (multilingual)
        $bookedPatterns = $this->regex(
            [
                'PRM assistance (requested|reserved|booked)',
                'Assistance (requested|reserved|booked)',
                'Help with boarding',
                'boarding assistance',
                '(PMR|PRM|handicap)\s*assistance',
                'assistance bestilt',
                'hjælp ved ombordstigning',
                'hjælp til ombordstigning',
                'kørestolsassistance|rullestolsassistance',
                'kørestol(?:\n|\r|\s)*assistance',
                'Serviceauftrag',
                'Mobilitätshilfe|Einstiegshilfe|Umsteigehilfe',
                'Assistenz (gebucht|angefordert)',
                'PRM-?Assisten[zt]',
                'Servizio di assistenza PMR',
                'Assistenza PMR (prenotata|richiesta)',
                'Mobilit[aà] ridotta',
                'Service d\'assistance (PRM|PMR)',
                'Assistance handicap[ée] (r[ée]serv[ée]e?|demand[ée]e?)',
                'Asistencia PMR',
                'Asistencia para personas con movilidad reducida',
            ]
        );
        foreach ($bookedPatterns as $re) {
            if (preg_match($re, $text)) { $evidence[] = $re . ''; $bookedHits++; }
        }
        // Consider structured fields that might contain assistance lines
        $fields = (array)($input['fields'] ?? []);
        foreach (['productNotes','reservationNotes'] as $fk) {
            $val = isset($fields[$fk]) ? (string)$fields[$fk] : '';
            if ($val !== '') { foreach ($bookedPatterns as $re) { if (preg_match($re, $val)) { $evidence[] = 'FIELD:' . mb_substr($val, 0, 80, 'UTF-8'); $bookedHits++; } } }
        }
        if (!empty($fields['serviceLines']) && is_array($fields['serviceLines'])) {
            foreach ((array)$fields['serviceLines'] as $line) {
                $v = (string)$line; if ($v === '') { continue; }
                foreach ($bookedPatterns as $re) { if (preg_match($re, $v)) { $evidence[] = 'FIELD:' . mb_substr($v, 0, 80, 'UTF-8'); $bookedHits++; } }
            }
        }

        // Discount patterns indicating handicap fare
        $discountPatterns = $this->regex(
            [
                '(disabilit(y|à)|invalid|handicap|schwerbehindert|reduced mobility)',
                'Erm[äa]ßigung:.*Schwerbehindert(e|en|er)',
                'Schwerbehindert(e|en|er)\s*50%','Carta\s*Blu','Disabilit[aà]',
                "carte d'handicap|handicap[ée]",
                'discapacidad|minusv[áa]lido|movilidad reducida',
                'ledsagerkort',
            ]
        );
        foreach ($discountPatterns as $re) { if (preg_match($re, $text)) { $evidence[] = $re . ''; $discountHits++; } }
        foreach (['fareName','fareCode'] as $fk) {
            $val = isset($fields[$fk]) ? (string)$fields[$fk] : '';
            if ($val !== '') { foreach ($discountPatterns as $re) { if (preg_match($re, $val)) { $evidence[] = 'FIELD:' . mb_substr($val, 0, 80, 'UTF-8'); $discountHits++; } } }
        }

        // Icon/symbol hints
        $iconPatterns = $this->regex([
            '\\x{267F}', // ♿
            'PMR|PRM',
            'wheel\\s*chair|wheelchair|rullestol|kørestol|körestol|rollstuhl|sedia a rotelle|fauteuil roulant|silla de ruedas',
        ]);
        foreach ($iconPatterns as $re) { if (preg_match($re, $text)) { $evidence[] = $re . ''; $iconHits++; } }

        // Vendor crumbs (if seller/operator hint provided)
        $seller = (string)($input['seller'] ?? '');
        if ($seller !== '') {
            $vendor = $this->normalizeVendor($seller);
            $crumbs = [
                'DB' => $this->regex(['Serviceauftrag','Mobilit[äa]tshilfe','Einstiegshilfe','Umsteigehilfe']),
                'Trenitalia' => $this->regex(['Servizio di assistenza PMR','Assistenza PMR','Mobilit[aà] ridotta']),
                'DSB' => $this->regex(['assistance','handicap','kørestol']),
                'SNCF' => $this->regex(["Service d'assistance",'handicap[ée]']),
            ];
            foreach ($crumbs[$vendor] ?? [] as $re) { if (preg_match($re, $text)) { $evidence[] = $re . ''; $bookedHits++; } }
        }

        // Secondary (soft) hint patterns – lower weight, reduce false negatives without over-inflating confidence
        $softPatterns = $this->regex([
            // Generic assistance words in multiple languages (avoid counting when already booked patterns matched)
            '\bassistance\b','\baide\b','\bayuda\b','\bassistenza\b','\bhjælp\b','\bhilfe\b',
            'mobilit[ée] réduite','persona con movilidad reducida','mobilit[aà] ridotta','reduced mobility',
        ]);
        $softHits = 0;
        foreach ($softPatterns as $re) { if (preg_match($re, $text)) { $softHits++; } }

        $pmrBooked = $bookedHits > 0;
        $pmrUser = $pmrBooked || $discountHits > 0 || $iconHits > 0 || ($softHits > 0 && ($discountHits + $iconHits) > 0);

        // Confidence model (heuristic):
        //  - Booked assistance dominates (0.85 baseline once any booked hit)
        //  - Each discount hit adds 0.35 (higher weight)
        //  - Each icon hit adds 0.25
        //  - Soft hints: 0.08 each (cap at +0.24) and only when not already booked
        $confidence = 0.0;
        if ($pmrBooked) { $confidence += 0.85; }
        $confidence += min(0.35 * $discountHits, 0.35 * 3); // cap discount contribution
        $confidence += min(0.25 * $iconHits, 0.25 * 2);      // cap icon contribution
        if (!$pmrBooked) { $confidence += min(0.08 * $softHits, 0.24); }

        // Vendor heuristics: gentle bump for DB Serviceauftrag only, and strong for Trenitalia PMR + servizio
        $seller = (string)($input['seller'] ?? '');
        if ($seller !== '') {
            $vendor = $this->normalizeVendor($seller);
            if ($vendor === 'DB' && preg_match('/serviceauftrag/i', $text) && !$pmrBooked) {
                $confidence = max($confidence, 0.35);
                $evidence[] = 'vendor:db-serviceauftrag';
            }
            if ($vendor === 'Trenitalia' && preg_match('/pmr/i', $text) && preg_match('/servizio|assistenza/i', $text) && !$pmrBooked) {
                $confidence = max($confidence, 0.85);
                $pmrBooked = true; $pmrUser = true;
                $evidence[] = 'vendor:trenitalia-pmr-servizio';
            }
        }
        $confidence = min(1.0, $confidence);
        $discountType = $this->extractDiscountFromText($textLow);

        return [
            'pmr_user' => $pmrUser,
            'pmr_booked' => $pmrBooked,
            'discount_type' => $discountType,
            'evidence' => array_values(array_unique($evidence)),
            'confidence' => round((float)$confidence, 2),
        ];
    }

    /** @return array<int, string> */
    private function regex(array $patterns): array
    {
        $out = [];
        foreach ($patterns as $p) { $out[] = '/' . $p . '/iu'; }
        return $out;
    }

    private function normalizeVendor(string $seller): string
    {
        $s = strtolower(trim($seller));
        if ($s === 'db' || str_contains($s, 'bahn') || str_contains($s, 'deutsche bahn')) { return 'DB'; }
        if (str_contains($s, 'trenitalia')) { return 'Trenitalia'; }
        if (str_contains($s, 'dsb')) { return 'DSB'; }
        if (str_contains($s, 'sncf')) { return 'SNCF'; }
        return 'other';
    }

    private function extractDiscountFromText(string $textLow): ?string
    {
        $pairs = [
            '/erm[äa]ßigung:.*schwerbehindert/iu' => 'DB: Schwerbehindert Ermäßigung',
            '/carta\s*blu/iu' => 'Trenitalia: Carta Blu',
            '/discapacidad/iu' => 'ES: discapacidad',
            '/handicap[ée]/iu' => 'FR: handicap',
        ];
        foreach ($pairs as $re => $label) { if (preg_match($re, $textLow)) { return $label; } }
        return null;
    }
}
