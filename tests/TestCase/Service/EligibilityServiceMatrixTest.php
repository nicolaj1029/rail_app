<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\EligibilityService;
use App\Service\ExemptionMatrixRepository;
use App\Service\ExemptionsRepository;
use App\Service\NationalOverridesRepository;
use Cake\TestSuite\TestCase;

final class EligibilityServiceMatrixTest extends TestCase
{
    private EligibilityService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new EligibilityService(
            new ExemptionsRepository(),
            new NationalOverridesRepository(),
            new ExemptionMatrixRepository()
        );
    }

    public static function provideCases(): array
    {
        return [
            'FR TGV G30 90min' => [
                ['country'=>'FR','scope'=>'long_domestic','operator'=>'SNCF','product'=>'TGV INOUI / Intercités (G30)','delayMin'=>90,'euOnly'=>true],
                ['percent'=>25,'source'=>'override']
            ],
            'DK DSB 35min' => [
                ['country'=>'DK','scope'=>'long_domestic','operator'=>'DSB','product'=>'Rejsetidsgaranti','delayMin'=>35,'euOnly'=>true],
                ['percent'=>25,'source'=>'override']
            ],
            'SE SJ regional 70min (<150 km profile triggers override tiers)' => [
                ['country'=>'SE','scope'=>'regional','operator'=>'SJ','product'=>'Förseningsersättning (national law)','delayMin'=>70,'euOnly'=>true],
                ['percent'=>25,'source'=>'override']
            ],
            'DE ICE Intl EU 75min (EU baseline)' => [
                ['country'=>'DE','scope'=>'intl_inside_eu','operator'=>'DB','product'=>'ICE','delayMin'=>75,'euOnly'=>true],
                ['percent'=>25,'source'=>'eu']
            ],
        ];
    }

    /**
     * @dataProvider provideCases
     * @param array<string,mixed> $ctx
     * @param array<string,mixed> $exp
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideCases')]
    public function testComputeCompensation(array $ctx, array $exp): void
    {
        $out = $this->svc->computeCompensation($ctx);
        $this->assertIsArray($out);
        $this->assertArrayHasKey('percent', $out);
        $this->assertSame($exp['percent'], $out['percent'], 'percent');
        if (isset($exp['source'])) {
            $this->assertSame($exp['source'], $out['source'] ?? null, 'source');
        }
    }
}
