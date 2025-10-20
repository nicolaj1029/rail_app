<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Art18RefusionEvaluator;
use PHPUnit\Framework\TestCase;

final class Art18RefusionEvaluatorDowngradeTest extends TestCase
{
    public function testDowngradeOutcomeAppears(): void
    {
        $svc = new Art18RefusionEvaluator();
        $journey = [ 'segments' => [ ['country' => 'DE', 'schedArr' => '2025-05-01T10:00:00', 'actArr' => '2025-05-01T10:45:00'] ] ];
        $res = $svc->evaluate($journey, [
            'fare_class_purchased' => '1',
            'class_delivered_status' => 'Lower',
        ]);
        $this->assertSame('Refusion pga. downgrade (Art.18 a–c)', $res['outcome']);
        $this->assertNotEmpty($res['limits']['downgrade'] ?? []);
    }
}
