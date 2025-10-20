<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\EligibilityService;
use App\Service\ExemptionsRepository;
use App\Service\NationalOverridesRepository;
use Cake\TestSuite\TestCase;

class EligibilityServiceTest extends TestCase
{
    public function testEuBaseline(): void
    {
        $svc = new EligibilityService(new ExemptionsRepository(), new NationalOverridesRepository());
        $r = $svc->computeCompensation([
            'delayMin' => 90,
            'euOnly' => true,
        ]);
        $this->assertSame(25, $r['percent']);
        $this->assertSame('eu', $r['source']);
    }

    public function testOverrideBeatsEu(): void
    {
        $svc = new EligibilityService(new ExemptionsRepository(), new NationalOverridesRepository());
        $r = $svc->computeCompensation([
            'delayMin' => 30,
            'euOnly' => true,
            'country' => 'France',
            'operator' => 'SNCF',
            'product' => 'TGV INOUI/Intercités',
        ]);
        $this->assertSame(25, $r['percent']); // override gives 25% at 30 min
        $this->assertSame('override', $r['source']);
    }

    public function testExtraordinaryDenies(): void
    {
        $svc = new EligibilityService(new ExemptionsRepository(), new NationalOverridesRepository());
        $r = $svc->computeCompensation([
            'delayMin' => 200,
            'euOnly' => true,
            'extraordinary' => true,
        ]);
        $this->assertSame(0, $r['percent']);
        $this->assertSame('denied', $r['source']);
    }
}
