<?php
declare(strict_types=1);

namespace App\Test\Case\Service;

use App\Service\ClaimCalculator;
use PHPUnit\Framework\TestCase;

final class ClaimCalculatorDowngradeTest extends TestCase
{
    public function testDowngradeAddsPartialRefund(): void
    {
        $calc = new ClaimCalculator();
        $out = $calc->calculate([
            'country_code' => 'DE',
            'currency' => 'EUR',
            'ticket_price_total' => 100.00,
            'trip' => [ 'through_ticket' => true, 'legs' => [ ['country' => 'DE', 'scheduled_arr' => '2025-01-01T12:00:00', 'actual_arr' => '2025-01-01T12:00:00'] ] ],
            'disruption' => [ 'delay_minutes_final' => 0 ],
            'fare_class_purchased' => '1',
            'class_delivered_status' => 'Lower',
        ]);
        $refund = $out['breakdown']['refund'] ?? [];
        $this->assertGreaterThan(0.0, $refund['amount'] ?? 0.0);
        $this->assertStringContainsString('Downgrade refund', (string)($refund['basis'] ?? ''));
    }
}
