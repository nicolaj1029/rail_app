<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Art. 19 standard bands:
 *  - 25% at 60–119 min
 *  - 50% at >=120 min
 *  Currency and amount rounding left simple; extend for national variations as needed.
 */
final class PerContractCompensation
{
    /**
     * @param float|null $ticketValue
     * @param int|null $delayMinutes
     * @param string|null $currency
     * @return array{band: 'NONE'|'25'|'50', percent: int, amount: float, currency: string|null}
     */
    public function compute(?float $ticketValue, ?int $delayMinutes, ?string $currency): array
    {
        if ($delayMinutes === null || $ticketValue === null) {
            return ['band' => 'NONE', 'percent' => 0, 'amount' => 0.0, 'currency' => $currency];
        }
        $percent = 0;
        if ($delayMinutes >= 120) {
            $percent = 50;
        } elseif ($delayMinutes >= 60) {
            $percent = 25;
        }
        $amount = round(($ticketValue * $percent) / 100, 2);
        return [
            'band' => $percent === 50 ? '50' : ($percent === 25 ? '25' : 'NONE'),
            'percent' => $percent,
            'amount' => $amount,
            'currency' => $currency,
        ];
    }
}
