<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\ClaimCalculator;
use App\Service\ExemptionProfileBuilder;
use App\Service\Art12Evaluator;
use PHPUnit\Framework\TestCase;

final class ClaimCalculatorTest extends TestCase
{
    public function testSkLongDomesticCompensationDeniedAndTotalsZero(): void
    {
        $calc = new ClaimCalculator();
        $out = $calc->calculate([
            'country_code' => 'SK',
            'currency' => 'EUR',
            'ticket_price_total' => 12.00,
            'service_scope' => 'long_domestic',
            'trip' => [
                'through_ticket' => true,
                'legs' => [
                    [
                        'from' => 'A', 'to' => 'B', 'country' => 'SK',
                        'scheduled_arr' => '2025-02-10T09:00:00',
                        'actual_arr' => '2025-02-10T10:05:00',
                    ],
                ],
            ],
            'disruption' => [ 'delay_minutes_final' => 65 ],
        ]);

        $comp = $out['breakdown']['compensation'] ?? [];
        $this->assertFalse($comp['eligible'] ?? true, 'Compensation should be ineligible');
        $this->assertSame(0, $comp['pct'] ?? -1, 'Compensation pct should be 0');
        $this->assertSame(0.0, $comp['amount'] ?? -1.0, 'Compensation amount should be 0');
        $this->assertSame('N/A', $comp['rule'] ?? '', 'Rule should be N/A when exempt');

        $totals = $out['totals'] ?? [];
        $this->assertSame(0.0, $totals['gross_claim'] ?? -1.0, 'Gross claim should be 0');
        $this->assertSame(0.0, $totals['service_fee_amount'] ?? -1.0, 'Service fee should be 0 when gross=0');
        $this->assertSame(0.0, $totals['net_to_client'] ?? -1.0, 'Net should be 0');

        $flags = $out['flags'] ?? [];
        $applied = $flags['exemptions_applied'] ?? [];
        $this->assertIsArray($applied);
        $this->assertContains('art19', $applied);
        $this->assertContains('art20_2', $applied);
        $this->assertContains('art30_2', $applied);
    }

    public function testPlIntlBeyondEuArt12AndArt10Exemptions(): void
    {
        // Profile banners for Art.10 and Art.12 applies=false via evaluator
        $journey = [
            'segments' => [[ 'country' => 'PL' ]],
            'is_international_beyond_eu' => true,
        ];
        $profile = (new ExemptionProfileBuilder())->build($journey);
        $this->assertFalse($profile['articles']['art10'] ?? true, 'Art.10 should be false for PL intl_beyond_eu');
        $this->assertFalse($profile['articles']['art12'] ?? true, 'Art.12 should be false for PL intl_beyond_eu');
        $this->assertNotEmpty($profile['ui_banners']);
        $this->assertTrue(
            (bool)array_filter($profile['ui_banners'], fn($b) => stripos((string)$b, 'Realtime-data') !== false),
            'Art.10 banner should be present when art10=false'
        );

        $art12 = (new Art12Evaluator())->evaluate($journey, []);
        $this->assertFalse($art12['art12_applies'], 'Art.12 should not apply under exemption');
    }
}
