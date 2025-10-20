<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\DowngradeComparator;
use PHPUnit\Framework\TestCase;

final class DowngradeComparatorTest extends TestCase
{
    public function testNoDowngrade(): void
    {
        $cmp = new DowngradeComparator();
        $res = $cmp->assess([
            'fare_class_purchased' => '2',
            'class_delivered_status' => 'Same',
            'reserved_amenity_delivered' => 'Ja',
            'facilities_delivered_status' => 'Ja',
        ]);
        $this->assertSame('none', $res['severity']);
        $this->assertSame(0.0, $res['suggested_pct']);
    }

    public function testClassDowngradeMajor(): void
    {
        $cmp = new DowngradeComparator();
        $res = $cmp->assess([
            'fare_class_purchased' => '1',
            'class_delivered_status' => 'Lower',
        ]);
        $this->assertSame('major', $res['severity']);
        $this->assertGreaterThanOrEqual(0.30, $res['suggested_pct']);
    }

    public function testAmenityMinor(): void
    {
        $cmp = new DowngradeComparator();
        $res = $cmp->assess([
            'reserved_amenity_delivered' => 'Nej',
        ]);
        $this->assertSame('minor', $res['severity']);
        $this->assertGreaterThan(0.0, $res['suggested_pct']);
        $this->assertLessThanOrEqual(0.50, $res['suggested_pct']);
    }
}
