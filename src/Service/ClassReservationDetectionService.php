<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Detect class purchased and reservation type from OCR ticket text.
 * Outputs normalized tokens aligned with UI fields in templates/Flow/one.php:
 *  - fare_class_purchased: '1'|'2'|'other'|'unknown'
 *  - berth_seat_type: 'seat'|'free'|'couchette'|'sleeper'|'none'|'unknown'
 * Also returns evidence[] and confidence 0..1.
 */
class ClassReservationDetectionService
{
    /**
     * @param array{rawText?:string, fields?:array} $input
     * @return array{fare_class_purchased:string,berth_seat_type:string,confidence:float,evidence:string[]}
     */
    public function analyze(array $input): array
    {
        $text = $this->normalize(implode("\n", array_filter([
            (string)($input['rawText'] ?? ''),
            (string)($input['fields']['fareName'] ?? ''),
            (string)($input['fields']['productName'] ?? ''),
            (string)($input['fields']['reservationLine'] ?? ''),
            (string)($input['fields']['seatLine'] ?? ''),
            (string)($input['fields']['coachSeatBlock'] ?? ''),
        ], fn($v)=>$v!=='')));

        $ev = [];
        $class = $this->detectClass($text, $ev);
        $resv  = $this->detectReservation($text, $ev);

        $conf = 0.3;
        if ($class !== 'unknown') { $conf += 0.35; }
        if ($resv !== 'unknown') { $conf += 0.35; }

        return [
            'fare_class_purchased' => $class,
            'berth_seat_type' => $resv,
            'confidence' => $this->round2(min(1.0, $conf)),
            'evidence' => array_slice(array_values(array_unique($ev)), 0, 10),
        ];
    }

    private function detectClass(string $text, array &$ev): string
    {
        $first = [
            '/\\b1\\.?\\s*klasse\\b/i',
            '/\\bfirst\\s*class\\b/i',
            '/\\b1\\.?\\s*klasse?\\b|klasse\\s*1\\b/i',
            '/\\b1[aª]?\\s*cl(asse|as[st])?\\b/i',
            '/\\bpremi(um|[èe]re)\\s*classe\\b/i',
            '/\\bbusiness\\s*(class)?\\b/i',
        ];
        $second = [
            '/\\b2\\.?\\s*klasse\\b/i',
            '/\\bsecond\\s*class\\b/i',
            '/\\b2\\.?\\s*klasse?\\b|klasse\\s*2\\b/i',
            '/\\b2[aª]?\\s*cl(asse|as[st])?\\b/i',
            '/\\bstandard(\\s*class)?\\b|econom(y|ico)\\b/i',
        ];
        if ($this->hitAny($text, $first, $ev)) return '1';
        if ($this->hitAny($text, $second, $ev)) return '2';
        if (preg_match('/\\bklasse|class|classe\\b/i', $text)) { $this->pushContextAuto($text, $ev); return 'other'; }
        return 'unknown';
    }

    private function detectReservation(string $text, array &$ev): string
    {
        $couchette = [ '/\\bligge?vogn\\b/i', '/\\bliegewagen\\b/i', '/\\bcouchette\\b/i' ];
        $sleeper   = [ '/\\bsovevogn\\b/i', '/\\bschlafwagen\\b/i', '/\\b(sleeper|coche\\s*cama|vagone\\s*letto)\\b/i' ];
        $seatFx    = [
            '/\\b(reservation|reserv[ée]e|reservado|prenotazione)\\b/i',
            '/\\bplatz(reservierung)?\\b/i',
            '/\\bfast\\s*s[æa]de\\b/i',
            '/\\bcoach\\s*\\d+\\s*seat\\s*\\w+\\b/i',
            '/\\bvogn\\s*\\d+\\s*plads\\s*\\w+\\b/i',
        ];
        $free      = [ '/\\bfri\\s*plads\\b/i', '/\\bopen\\s*seating\\b/i', '/\\bfreie?r?\\s*sitz\\b/i', '/\\bsi[ée]ges?\\s*libres?\\b/i' ];
        $none      = [ '/\\bingen\\s*(plads|reservation)\\b/i', '/\\bno\\s*(seat|reservation)\\b/i' ];

        if ($this->hitAny($text, $couchette, $ev)) return 'couchette';
        if ($this->hitAny($text, $sleeper, $ev))   return 'sleeper';
        if ($this->hitAny($text, $seatFx, $ev))    return 'seat';
        if ($this->hitAny($text, $free, $ev))      return 'free';
        if ($this->hitAny($text, $none, $ev))      return 'none';
        if (preg_match('/\\b(vogn|coach)\\s*\\d+.*(plads|seat)\\s*\\w+/i', $text, $m, PREG_OFFSET_CAPTURE)) {
            $this->pushContext($text, (int)$m[0][1], strlen($m[0][0]), $ev); return 'seat';
        }
        return 'unknown';
    }

    private function hitAny(string $text, array $patterns, array &$ev): bool
    {
        $ok = false;
        foreach ($patterns as $rx) {
            if (preg_match($rx, $text, $m, PREG_OFFSET_CAPTURE)) {
                $ok = true; $this->pushContext($text, (int)$m[0][1], strlen($m[0][0]), $ev);
            }
        }
        return $ok;
    }

    private function pushContext(string $s, int $idx, int $len, array &$ev, int $pad = 18): void
    {
        $start = max(0, $idx - $pad); $end = min(strlen($s), $idx + $len + $pad);
        $ev[] = '…' . substr($s, $start, $end - $start) . '…';
    }
    private function pushContextAuto(string $s, array &$ev): void
    {
        if (strlen($s) > 0) { $ev[] = '…' . substr($s, 0, min(36, strlen($s))) . '…'; }
    }
    private function normalize(string $s): string { return trim(preg_replace('/\s+/', ' ', $s) ?? $s); }
    private function round2(float $n): float { return round($n * 100) / 100; }
}
