<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Detect bicycle booking/reservations from OCR text and optional structured hints.
 * Returns simple flags and evidence, suitable for driving TRIN 3 display and TRIN 9 prompts.
 */
class BikeDetectionService
{
    /**
     * Analyze OCR text and hints to detect bike-related facts.
     * @return array{
     *   bike_booked:bool,
     *   bike_count:?int,
     *   operator_hint:?string,
     *   evidence:array<int,string>,
     *   confidence:float,
     *   bike_res_required?:'yes'|'no'|'unknown',
     *   bike_reservation_type?:'separate'|'included'|'not_required'
     * }
     */
    public function analyze(array $input): array
    {
        $rawText = (string)($input['rawText'] ?? '');
        $text = preg_replace('/\s+/u', ' ', $rawText) ?? $rawText;
        $textLow = mb_strtolower($text, 'UTF-8');
        $evidence = [];
        $hits = 0;

        // Core patterns by language
        $pats = $this->regex([
            // DE
            '\\bFahrrad(karte|mitnahme)\\b','\\bFahrrad(reservierung|platz|stellplatz)\\b','\\bRads?reservierung\\b',
            // DA
            '\\bCykel(plads)?billet\\b','\\bCykel(plads|reservation)\\b',
            // FR
            '\\b(réservation|reservation)\\s*v(é|e)lo\\b','\\bplace\\s*v(é|e)lo\\b',
            // IT
            '\\b(bici|bicicletta)\\s+al\\s*seguito\\b','\\btrasporto\\s*bici\\b','\\bprenotazione\\s*bici\\b',
            // Generic icon/english
            '\\xF0\\x9F\\x9A\\xB2', // 🚲 utf-8 bytes
            '\\bbike\\s*(reservation|space)\\b',
        ]);
        foreach ($pats as $re) { if (preg_match($re, $text)) { $evidence[] = $this->sample($text, $re); $hits++; } }

        // Count patterns (x2, Qty: 2, 2 velos, 2 Fahrräder)
        $count = null;
        $countPats = $this->regex([
            '\\b(x|qty\\s*[:=]?|anzahl\\s*[:=]?|nombre\\s*[:=]?|numero\\s*[:=]?)\\s*(\\d{1,2})\\b',
            '\\b(\\d{1,2})\\s*(fahrr(ä|a)der|velos?|biciclette|bikes?)\\b',
        ]);
        foreach ($countPats as $re) {
            if (preg_match($re, $text, $m)) { $num = (int)$m[count($m)-1]; if ($num > 0) { $count = $num; $evidence[] = 'COUNT:' . $m[0]; break; } }
        }

        // Vendor hint from seller/operator string
        $seller = (string)($input['seller'] ?? '');
        $hint = null; $s = mb_strtolower($seller, 'UTF-8');
        if ($s !== '') {
            if ($s === 'db' || str_contains($s, 'bahn') || str_contains($s, 'deutsche bahn')) { $hint = 'DB'; }
            elseif (str_contains($s, 'dsb')) { $hint = 'DSB'; }
            elseif (str_contains($s, 'sncf') || str_contains($s, 'oui')) { $hint = 'SNCF'; }
            elseif (str_contains($s, 'trenitalia')) { $hint = 'Trenitalia'; }
            elseif (str_contains($s, 'öbb') || str_contains($s, 'oebb')) { $hint = 'OEBB'; }
        }

        // Barcode text hint (best-effort)
        $barcode = (string)($input['barcodeText'] ?? '');
        if ($barcode !== '') {
            if (preg_match('/(bike|velo|vélo|fahrrad|bici)/iu', $barcode)) { $evidence[] = 'BARCODE:HINT'; $hits++; }
            if (preg_match('/bike[s ]*[:= ]*(\d{1,2})/i', $barcode, $m)) { $c=(int)$m[1]; if ($c>0) { $count=$c; $evidence[]='BARCODE:COUNT:' . $m[0]; } }
        }

        // Derive reservation requirement and type heuristically
        $resReq = 'unknown'; // yes|no|unknown
        $resType = null;     // separate|included|not_required|null

        // Strong phrases: reservation required
        $reqPats = $this->regex([
            // EN/DK/DE/FR/IT
            '\bbike\s*reservation\s*(required|needed)\b',
            'reservation\s+p[åa]kr[æe]vet',
            'reservierung\s*pflicht|reservierung\s*erforderlich',
            '(r[ée]servation)\s*v[ée]lo\s*(obligatoire|requise)',
            'prenotazione\s*bici\s*(obbligatoria|richiesta)'
        ]);
        foreach ($reqPats as $re) { if (preg_match($re, $text)) { $resReq = 'yes'; $evidence[] = 'RESREQ:' . $this->sample($text, $re); break; } }

        // Strong phrases: NOT required
        $noReqPats = $this->regex([
            '\bno\s+bike\s*reservation\b',
            'ingen\s*cykel\s*reservation',
            'ohne\s*reservierung\s*fahrrad',
            'sans\s*r[ée]servation\s*v[ée]lo',
            'senza\s*prenotazione\s*bici'
        ]);
        foreach ($noReqPats as $re) { if (preg_match($re, $text)) { $resReq = 'no'; $resType = 'not_required'; $evidence[] = 'RESNO:' . $this->sample($text, $re); break; } }

        // Reservation type: separate vs included
        if ($resType === null) {
            $sepPats = $this->regex([
                '\bbike\s*reservation\b',
                '\bcykel\s*reservation\b',
                '\bfahrrad(reservierung|platz)\b',
                '(r[ée]servation)\s*v[ée]lo',
                'prenotazione\s*bici'
            ]);
            foreach ($sepPats as $re) { if (preg_match($re, $text)) { $resType = 'separate'; $evidence[] = 'RESTYPE:separate:' . $this->sample($text, $re); break; } }
        }
        if ($resType === null) {
            $inclPats = $this->regex([
                '\b(bike|cykel)\s*(included|inkluderet|inklusive)\b',
                '\bfahrrad\s*(inklusive|inkludiert)\b',
                '\bv[ée]lo\s*(inclus)\b'
            ]);
            foreach ($inclPats as $re) { if (preg_match($re, $text)) { $resType = 'included'; $evidence[] = 'RESTYPE:included:' . $this->sample($text, $re); break; } }
        }

        // Operator/product heuristics as tie-breakers
        if ($resReq === 'unknown') {
            $isDB = ($hint === 'DB') || str_contains($textLow, 'deutsche bahn') || preg_match('/\b(ice|ic|ec)\b/i', $text);
            $isSNCF = ($hint === 'SNCF') || preg_match('/\b(tgv|inoui|ouigo)\b/i', $text);
            $isDSB = ($hint === 'DSB') || preg_match('/\b(ic|lyntog)\b/i', $textLow);
            if ($isDB || $isSNCF || $isDSB) {
                $resReq = 'yes';
                if ($resType === null) { $resType = 'separate'; }
                $evidence[] = 'RESREQ:operator_rule';
            }
        }

        // If we detected a separate reservation mention, res required is very likely yes
        if ($resReq === 'unknown' && $resType === 'separate') { $resReq = 'yes'; }

    $booked = $hits > 0;
    // Confidence heuristic adjusted: lower baseline for absence, slightly higher per-hit weight.
    // Previous formula: 0.55 + 0.15*hits + 0.1(count)
    // New formula: base (0.10 if no hits, else 0.30) + 0.18*hits (cap 3) + 0.10 if count inferred.
    // Rationale: A ticket with no bike indicators should not sit at mid confidence (0.55);
    // positive evidence still reaches >0.55 quickly (e.g. 2 hits -> 0.30 + 0.36 = 0.66).
    $base = $hits > 0 ? 0.30 : 0.10;
    $confidence = $base + 0.18 * min($hits, 3) + ($count ? 0.10 : 0.0);
    $confidence = min(1.0, $confidence);

        $out = [
            'bike_booked' => $booked,
            'bike_count' => $count,
            'operator_hint' => $hint,
            'evidence' => array_values(array_unique($evidence)),
            'confidence' => round($confidence, 2),
        ];
        if ($resReq !== 'unknown') { $out['bike_res_required'] = $resReq; }
        if ($resType !== null) { $out['bike_reservation_type'] = $resType; }
        return $out;
    }

    /** @return array<int,string> */
    private function regex(array $patterns): array
    {
        $out = [];
        foreach ($patterns as $p) { $out[] = '/' . $p . '/iu'; }
        return $out;
    }

    private function sample(string $text, string $regex): string
    {
        if (preg_match($regex, $text, $m, PREG_OFFSET_CAPTURE)) {
            $start = max(0, ($m[0][1] ?? 0) - 15);
            $end = min(strlen($text), ($m[0][1] ?? 0) + strlen($m[0][0]) + 15);
            return '…' . substr($text, $start, $end - $start) . '…';
        }
        return 'HIT';
    }
}
