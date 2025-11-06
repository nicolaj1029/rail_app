<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Detects fare flexibility and train specificity from OCR'd ticket text.
 * Emits normalized tokens aligned with UI:
 *  - fare_flex_type: nonflex | semiflex | flex | pass | other
 *  - train_specificity: specific | any_day | unknown
 * Also returns confidence 0..1 and a small set of evidence snippets.
 */
class TicketTypeDetectionService
{
    /** @return array{fare_flex_type:string,train_specificity:string,confidence:float,evidence:string[]} */
    public function analyze(array $input): array
    {
        $raw = $this->normalize(implode("\n", array_filter([
            (string)($input['rawText'] ?? ''),
            (string)($input['fareName'] ?? ''),
            (string)($input['fareCode'] ?? ''),
            (string)($input['productName'] ?? ''),
            (string)($input['seatLine'] ?? ''),
            (string)($input['validityLine'] ?? ''),
            (string)($input['reservationLine'] ?? ''),
        ], fn($v)=>$v!=='')));

        $ev = [];

        // Subscriptions / passes trump other signals
        $isPass = $this->hitAny($raw, [
            '/\b(abonnement|periodekort|season\s*ticket|travel\s*pass|pass)\b/i',
            '/\bCarta\s*Freccia\b/i',
            '/\bGeneralabonnement|BahnCard\b/i',
        ], $ev);
        if ($isPass) {
            $fare = 'pass';
            $scope = $this->decideScope($raw, $ev);
            $conf = 0.9;
            return [
                'fare_flex_type' => $fare,
                'train_specificity' => $scope,
                'confidence' => $this->round2($conf),
                'evidence' => array_slice(array_values(array_unique($ev)), 0, 10),
            ];
        }

        $isFlex = $this->hitAny($raw, [
            // Generic
            '/\bflex(ibel|ibility|preis|price|tarif)\b/i',
            '/\bfree\s*(refund|exchange)\b/i',
            '/refundable\b.*\bany time/i',
            // DB
            '/\bFlexpreis\b/i',
            // SNCF
            '/\b(Billet|Tarif).*Flex\b/i',
            '/\b(remboursable|échangeable)\s*(sans|sans frais|à tout moment)\b/i',
            // DSB
            '/\b(fuldt\s*refunderbar|fuld\s*refusion)\b/i',
            // Trenitalia
            '/\bFlex(i|)\b/i',
        ], $ev);

        $isSemi = $this->hitAny($raw, [
            // Generic
            '/\b(semi|partly)\s*flex\b/i',
            '/\bexchange(able)?\b.*(fee|charge|until)/i',
            '/\brefundable\b.*(fee|partial|until)/i',
            // DB
            '/\bSparpreis\b/i',
            // SNCF
            '/\b(Billet|Tarif).*(Modul|Semi|Loisir)\b/i',
            '/\béchangeable\b/i',
            // DSB
            '/\bFlex?bil\s*med\s*gebyr\b/i',
            // Trenitalia
            '/\bEconom(y|ico)\b/i',
        ], $ev);

        $isNon = $this->hitAny($raw, [
            // Generic
            '/\bnon-?flex\b/i',
            '/\bno\s*(refund|exchange)\b/i',
            '/\bikke\s*refunderbar\b/i',
            // DB
            '/\bSuper\s*Sparpreis\b/i',
            // SNCF
            '/\bnon\s*(remboursable|échangeable)\b/i',
            // DSB
            '/\bOrange\b/i',
            // Trenitalia
            '/\bSuper\s*Econom(y|ico)\b/i',
        ], $ev);

        $fare = $this->decideFare($isFlex, $isSemi, $isNon);
        $scope = $this->decideScope($raw, $ev);

        $conf = 0.4;
        if ($fare !== 'other') { $conf += 0.3; }
        if ($scope !== 'unknown') { $conf += 0.3; }

        return [
            'fare_flex_type' => $fare,
            'train_specificity' => $scope,
            'confidence' => $this->round2(min(1.0, $conf)),
            'evidence' => array_slice(array_values(array_unique($ev)), 0, 10),
        ];
    }

    private function decideFare(bool $isFlex, bool $isSemi, bool $isNon): string
    {
        if ($isNon && !$isFlex) return 'nonflex';
        if ($isFlex && !$isNon) return $isSemi ? 'semiflex' : 'flex';
        if ($isSemi) return 'semiflex';
        return 'other';
    }

    private function decideScope(string $text, array &$ev): string
    {
        $specific = $this->hitAny($text, [
            // Generic / multilingual
            '/only\s*valid\s*for\s*(this|the\s*booked)\s*train/i',
            '/gilt\s*nur\s*im\s*gebuchten\s*Zug/i',
            '/valable\s*uniquement\s*sur\s*le\s*train/i',
            '/solo\s*per\s*il\s*treno\s*prenotato/i',
            '/kun\s*for\s*(dette|det)\s*tog/i',
            '/res(ervation)?\s*obligatoire\b.*(train|num[eé]ro)/i',
            // Explicit train number + reserved seat often implies specificity
            '/\b(train|zug|nr\.?|no\.?|numero)\s*[A-Z]?\s*\d{2,6}\b/i',
            '/\b(seat|plads|platz|si[eè]ge)\b/i',
        ], $ev);
        $anyDay = $this->hitAny($text, [
            '/\bany\s*train\s*(that\s*day|same\s*day)\b/i',
            '/g[aä]ltig\s*am\s*geltungstag\b/i',
            '/valable\s*(le\s*jour|toute\s*la\s*journ[eé]e)\b/i',
            '/valido\s*(per\s*la\s*giornata|tutta\s*la\s*giornata)\b/i',
            '/gyldig\s*hele\s*dagen\b/i',
            '/\bopen\s*ticket\b/i',
        ], $ev);

        if ($specific && !$anyDay) return 'specific';
        if ($anyDay && !$specific) return 'any_day';

        // Heuristic: TrainNo + Seat + Time -> specific
        $hasTrainNo = (bool)preg_match('/\b(train|zug|nr\.?|no\.?|numero)\s*[A-Z]?\s*\d{2,6}\b/i', $text);
        $hasSeat    = (bool)preg_match('/\b(seat|plads|platz|siege|si[eè]ge)\b/i', $text);
        $hasTime    = (bool)preg_match('/\b([01]?\d|2[0-3])[:h][0-5]\d\b/', $text);
        if ($hasTrainNo && $hasSeat && $hasTime) {
            $ev[] = 'HEURISTIC:TrainNo+Seat+Time';
            return 'specific';
        }
        return 'unknown';
    }

    private function hitAny(string $text, array $patterns, array &$ev): bool
    {
        $ok = false;
        foreach ($patterns as $rx) {
            if (preg_match($rx, $text, $m, PREG_OFFSET_CAPTURE)) {
                $ok = true;
                $this->pushContext($text, (int)$m[0][1], strlen($m[0][0]), $ev);
            }
        }
        return $ok;
    }

    private function pushContext(string $s, int $idx, int $len, array &$ev, int $pad = 18): void
    {
        $start = max(0, $idx - $pad); $end = min(strlen($s), $idx + $len + $pad);
        $ev[] = '…' . substr($s, $start, $end - $start) . '…';
    }

    private function normalize(string $s): string
    {
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return trim($s);
    }

    private function round2(float $n): float { return round($n * 100) / 100; }
}
