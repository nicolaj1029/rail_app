<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Rail\RailDepartureSearchService;
use Cake\TestSuite\TestCase;

final class RailDepartureSearchServiceTest extends TestCase
{
    public function testBuildLookupSignatureIgnoresHints(): void
    {
        $service = new RailDepartureSearchService([]);

        $base = [
            'from_station' => 'Kobenhavn H',
            'to_station' => 'Hamburg Hbf',
            'date' => '2026-05-04',
            'time' => '08:00',
            'operator_hint' => 'DSB',
            'train_number_hint' => 'EC 399',
            'locale' => 'da-DK',
        ];
        $changedHints = $base;
        $changedHints['operator_hint'] = 'DB';
        $changedHints['train_number_hint'] = 'ICE 73';

        $this->assertSame(
            $service->buildLookupSignature($base),
            $service->buildLookupSignature($changedHints)
        );
    }

    public function testBuildLookupSignatureChangesWhenRouteChanges(): void
    {
        $service = new RailDepartureSearchService([]);

        $base = [
            'from_station' => 'Kobenhavn H',
            'to_station' => 'Hamburg Hbf',
            'date' => '2026-05-04',
            'time' => '08:00',
            'locale' => 'da-DK',
        ];
        $changedRoute = $base;
        $changedRoute['time'] = '09:00';

        $this->assertNotSame(
            $service->buildLookupSignature($base),
            $service->buildLookupSignature($changedRoute)
        );
    }

    public function testBuildCriteriaFromFormIncludesLookupIds(): void
    {
        $service = new RailDepartureSearchService([]);

        $criteria = $service->buildCriteriaFromForm([
            'dep_station' => 'Kobenhavn H',
            'dep_station_lookup_id' => '8601309',
            'arr_station' => 'Barcelona Sants',
            'arr_station_lookup_id' => '71801',
            'dep_date' => '2026-05-15',
            'dep_time' => '08:00',
        ]);

        $this->assertSame('8601309', $criteria['from_station_id']);
        $this->assertSame('71801', $criteria['to_station_id']);
        $this->assertSame('Kobenhavn H', $criteria['from_station']);
        $this->assertSame('Barcelona Sants', $criteria['to_station']);
    }
}
