<?php
declare(strict_types=1);

namespace App\Service;

class PaymentsService
{
    /**
     * Simulate an instant payout and return reference and status.
     * In production, integrate a real PSP and handle errors and webhooks.
     *
     * @param float $amount
     * @param string $currency
     * @param array{name?:string,email?:string} $recipient
     * @return array{status:string, reference:string}
     */
    public function payout(float $amount, string $currency, array $recipient): array
    {
        // Simple sanity
        if ($amount <= 0) {
            return ['status' => 'skipped', 'reference' => ''];
        }
        $ref = 'SIM-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('YmdHis');
        return ['status' => 'paid', 'reference' => $ref];
    }
}
