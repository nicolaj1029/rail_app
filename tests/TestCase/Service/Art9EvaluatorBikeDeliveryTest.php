<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Art9Evaluator;
use Cake\TestSuite\TestCase;

final class Art9EvaluatorBikeDeliveryTest extends TestCase
{
    public function testBikeDeniedInfluencesPart3(): void
    {
        $svc = new Art9Evaluator();
        $journey = ['segments' => [['country' => 'DE']]];
        $meta = [
            'station_board_updates' => 'unknown',
            'onboard_announcements' => 'unknown',
            'disruption_updates_frequency' => 'unknown',
            'assistance_contact_visible' => 'unknown',
            'realtime_info_seen' => 'unknown',
            'bike_denied_reason' => 'Capacity',
            'bike_followup_offer' => 'Nej'
        ];
        $res = $svc->evaluate($journey, $meta);
        $this->assertSame(false, $res['parts']['art9_3_ok']);
        $this->assertSame('Nej', $res['hooks']['info_during_disruption']);
    }

    public function testBikeDelayMarksPartial(): void
    {
        $svc = new Art9Evaluator();
        $journey = ['segments' => [['country' => 'DE']]];
        $meta = [
            'station_board_updates' => 'unknown',
            'onboard_announcements' => 'unknown',
            'disruption_updates_frequency' => 'unknown',
            'assistance_contact_visible' => 'unknown',
            'realtime_info_seen' => 'unknown',
            'bike_delay_bucket' => '>60'
        ];
        $res = $svc->evaluate($journey, $meta);
        $this->assertNull($res['parts']['art9_3_ok']); // baseline checks still unknown, but parent should be set
        $this->assertSame('Delvist', $res['hooks']['info_during_disruption']);
    }
}
