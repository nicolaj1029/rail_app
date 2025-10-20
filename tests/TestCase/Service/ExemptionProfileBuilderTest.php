<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\ExemptionProfileBuilder;
use Cake\TestSuite\TestCase;

class ExemptionProfileBuilderTest extends TestCase
{
    public function testFranceRegionalDisablesArt19(): void
    {
        $builder = new ExemptionProfileBuilder();
        $journey = [
            'segments' => [ ['country' => 'FR'] ],
            'is_international_inside_eu' => false,
            'is_international_beyond_eu' => false,
            'is_long_domestic' => false,
        ];
        $profile = $builder->build($journey);
        $this->assertSame('regional', $profile['scope']);
        $this->assertFalse($profile['articles']['art19']);
    $this->assertStringContainsString('FR regional', implode(' ', $profile['notes']));
    }

    public function testPolandRegionalDisablesArt19And20(): void
    {
        $builder = new ExemptionProfileBuilder();
        $journey = [
            'segments' => [ ['country' => 'PL'] ],
            'is_international_inside_eu' => false,
            'is_international_beyond_eu' => false,
            'is_long_domestic' => false,
        ];
        $profile = $builder->build($journey);
        $this->assertSame('regional', $profile['scope']);
        $this->assertFalse($profile['articles']['art19']);
        $this->assertFalse($profile['articles']['art20_2']);
    }
}
