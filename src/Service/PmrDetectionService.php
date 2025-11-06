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
                '(PMR|PRM|handicap)\s*assistance',
                'assistance bestilt',
                'hjælp ved ombordstigning',
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
                'discapacidad|movilidad reducida',
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
            'wheelchair|rullestol|kørestol|rollstuhl|sedia a rotelle|fauteuil roulant|silla de ruedas',
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

        $pmrBooked = $bookedHits > 0;
        $pmrUser = $pmrBooked || $discountHits > 0 || $iconHits > 0;
        $confidence = min(1.0, ($pmrBooked ? 0.85 : 0.0) + $discountHits * 0.30 + $iconHits * 0.25);
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
