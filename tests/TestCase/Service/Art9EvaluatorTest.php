<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Art9Evaluator;
use Cake\TestSuite\TestCase;

class Art9EvaluatorTest extends TestCase
{
    private function baseProfile(array $over = []): array
    {
        $base = [
            'scope' => 'international',
            'articles' => [
                'art12' => true,
                'art17' => true,
                'art18_3' => true,
                'art19' => true,
                'art20_2' => true,
                'art30_2' => true,
                'art10' => true,
                'art9' => true,
            ],
            'articles_sub' => [ 'art9_1' => true, 'art9_2' => true, 'art9_3' => true ],
            'notes' => [],
            'ui_banners' => [],
        ];
        return array_replace_recursive($base, $over);
    }

    public function testAllYesOverallTrue(): void
    {
        $svc = new Art9Evaluator();
        $meta = [
            '_profile_override' => $this->baseProfile(),
            'info_before_purchase' => 'Ja',
            'language_accessible' => 'Ja',
            'accessibility_format' => 'Ja',
            'multi_channel_information' => 'Ja',
            'accessible_formats_offered' => 'Ja',
            'info_on_rights' => 'Ja',
            'rights_notice_displayed' => 'Ja',
            'rights_contact_provided' => 'Ja',
            'info_during_disruption' => 'Ja',
            'station_board_updates' => 'Ja',
            'onboard_announcements' => 'Ja',
            'disruption_updates_frequency' => 'Ja',
            'assistance_contact_visible' => 'Ja',
        ];
        $res = $svc->evaluate([], $meta);
        $this->assertTrue($res['parts']['art9_1_ok']);
        $this->assertTrue($res['parts']['art9_2_ok']);
        $this->assertTrue($res['parts']['art9_3_ok']);
        $this->assertTrue($res['art9_ok']);
    }

    public function testPart2FailureOverallFalse(): void
    {
        $svc = new Art9Evaluator();
        $meta = [
            '_profile_override' => $this->baseProfile(),
            'info_before_purchase' => 'Ja',
            'language_accessible' => 'Ja',
            'accessibility_format' => 'Ja',
            'multi_channel_information' => 'Ja',
            'accessible_formats_offered' => 'Ja',
            'info_on_rights' => 'Nej', // triggers failure in part 2
            'rights_notice_displayed' => 'Ja',
            'rights_contact_provided' => 'Ja',
            'info_during_disruption' => 'Ja',
            'station_board_updates' => 'Ja',
            'onboard_announcements' => 'Ja',
            'disruption_updates_frequency' => 'Ja',
            'assistance_contact_visible' => 'Ja',
        ];
        $res = $svc->evaluate([], $meta);
        $this->assertTrue($res['parts']['art9_1_ok']);
        $this->assertFalse($res['parts']['art9_2_ok']);
        $this->assertTrue($res['parts']['art9_3_ok']);
        $this->assertFalse($res['art9_ok']);
    }

    public function testUnknownPartGivesNull(): void
    {
        $svc = new Art9Evaluator();
        $meta = [
            '_profile_override' => $this->baseProfile(),
            'info_before_purchase' => 'Ja',
            'language_accessible' => 'Ja',
            'accessibility_format' => 'Ja',
            'multi_channel_information' => 'Ja',
            'accessible_formats_offered' => 'Ja',
            'info_on_rights' => 'Ja',
            'rights_notice_displayed' => 'Ja',
            'rights_contact_provided' => 'Ja',
            // part 3 incomplete (unknowns)
            'info_during_disruption' => 'unknown',
            'station_board_updates' => 'unknown',
            'onboard_announcements' => 'unknown',
            'disruption_updates_frequency' => 'unknown',
            'assistance_contact_visible' => 'unknown',
        ];
        $res = $svc->evaluate([], $meta);
        $this->assertTrue($res['parts']['art9_1_ok']);
        $this->assertTrue($res['parts']['art9_2_ok']);
        $this->assertNull($res['parts']['art9_3_ok']);
        $this->assertNull($res['art9_ok']);
    }

    public function testSelectiveExemption(): void
    {
        // Exempt part 2 only
        $profile = $this->baseProfile([
            'articles_sub' => ['art9_1' => true, 'art9_2' => false, 'art9_3' => true],
        ]);
        $svc = new Art9Evaluator();
        $meta = [
            '_profile_override' => $profile,
            // part 1 all yes
            'info_before_purchase' => 'Ja',
            'language_accessible' => 'Ja',
            'accessibility_format' => 'Ja',
            'multi_channel_information' => 'Ja',
            'accessible_formats_offered' => 'Ja',
            // part 2 (ignored / exempt) deliberately failing
            'info_on_rights' => 'Nej',
            'rights_notice_displayed' => 'Nej',
            'rights_contact_provided' => 'Nej',
            // part 3 all yes
            'info_during_disruption' => 'Ja',
            'station_board_updates' => 'Ja',
            'onboard_announcements' => 'Ja',
            'disruption_updates_frequency' => 'Ja',
            'assistance_contact_visible' => 'Ja',
        ];
        $res = $svc->evaluate([], $meta);
        $this->assertTrue($res['parts']['art9_1_ok']);
        $this->assertNull($res['parts']['art9_2_ok']); // exempt
        $this->assertTrue($res['parts']['art9_3_ok']);
        $this->assertTrue($res['art9_ok']); // failures were only in exempt part
    }

    public function testParentDerivationFromSubHooks(): void
    {
        $svc = new Art9Evaluator();
        $meta = [
            '_profile_override' => $this->baseProfile(),
            // Parent unknown, but all sub-hooks known and positive -> derive 'Ja'
            'info_before_purchase' => 'unknown',
            'language_accessible' => 'Ja',
            'accessibility_format' => 'Ja',
            'multi_channel_information' => 'Ja',
            'accessible_formats_offered' => 'Ja',
            'info_on_rights' => 'unknown',
            'rights_notice_displayed' => 'Ja',
            'rights_contact_provided' => 'Ja',
            'complaint_channel_seen' => 'Ja',
            'info_during_disruption' => 'unknown',
            'station_board_updates' => 'Ja',
            'onboard_announcements' => 'Ja',
            'disruption_updates_frequency' => 'Ja',
            'assistance_contact_visible' => 'Ja',
            'realtime_info_seen' => 'Ja',
        ];
        $res = $svc->evaluate([], $meta);
        $this->assertSame('Ja', $res['hooks']['info_before_purchase']);
        $this->assertSame('Ja', $res['hooks']['info_on_rights']);
        $this->assertSame('Ja', $res['hooks']['info_during_disruption']);
        $this->assertTrue($res['art9_ok']);
    }

    public function testParentDerivationNejFromAnyNo(): void
    {
        $svc = new Art9Evaluator();
        $meta = [
            '_profile_override' => $this->baseProfile(),
            'info_on_rights' => 'unknown',
            'rights_notice_displayed' => 'Nej',
            'rights_contact_provided' => 'Ja',
            'complaint_channel_seen' => 'Ja',
        ];
        $res = $svc->evaluate([], $meta);
        $this->assertSame('Nej', $res['hooks']['info_on_rights']);
        $this->assertFalse($res['art9_ok']);
    }

    public function testWeakPositiveFromPreinformedDisruption(): void
    {
        $svc = new Art9Evaluator();
        $meta = [
            '_profile_override' => $this->baseProfile(),
            'info_before_purchase' => 'unknown',
            'preinformed_disruption' => 'Ja',
            // keep other considered 9(1) subhooks unknown so it resolves to Delvist
            'language_accessible' => 'unknown',
            'accessibility_format' => 'unknown',
            'multi_channel_information' => 'unknown',
            'accessible_formats_offered' => 'unknown',
            // Fill other parts to avoid overall unknown
            'info_on_rights' => 'Ja',
            'rights_notice_displayed' => 'Ja',
            'rights_contact_provided' => 'Ja',
            'info_during_disruption' => 'Ja',
            'station_board_updates' => 'Ja',
            'onboard_announcements' => 'Ja',
            'disruption_updates_frequency' => 'Ja',
            'assistance_contact_visible' => 'Ja',
        ];
        $res = $svc->evaluate([], $meta);
        $this->assertSame('Delvist', $res['hooks']['info_before_purchase']);
        $this->assertNull($res['parts']['art9_1_ok']); // derivation is weak, part still unknown
        $this->assertNull($res['art9_ok']);
    }

    public function testExtendedNegativesForceParentNo(): void
    {
        $svc = new Art9Evaluator();
        $meta = [
            '_profile_override' => $this->baseProfile(),
            // Parent unknown and base-considered subhooks unknown
            'info_before_purchase' => 'unknown',
            'language_accessible' => 'unknown',
            'accessibility_format' => 'unknown',
            'multi_channel_information' => 'unknown',
            'accessible_formats_offered' => 'unknown',
            // One extended key explicitly negative
            'multiple_fares_shown' => 'Nej',
            // Other parts positive so overall depends on part 1
            'info_on_rights' => 'Ja',
            'rights_notice_displayed' => 'Ja',
            'rights_contact_provided' => 'Ja',
            'info_during_disruption' => 'Ja',
            'station_board_updates' => 'Ja',
            'onboard_announcements' => 'Ja',
            'disruption_updates_frequency' => 'Ja',
            'assistance_contact_visible' => 'Ja',
        ];
        $res = $svc->evaluate([], $meta);
        $this->assertSame('Nej', $res['hooks']['info_before_purchase']);
        $this->assertFalse($res['parts']['art9_1_ok']);
        $this->assertFalse($res['art9_ok']);
    }

    public function testNestedFlowChartMetaIsFlattened(): void
    {
        $svc = new Art9Evaluator();
        $meta = [
            '_profile_override' => $this->baseProfile(),
            // Leave top-level unknown
            'info_before_purchase' => 'unknown',
            'language_accessible' => 'unknown',
            'accessibility_format' => 'unknown',
            'multi_channel_information' => 'unknown',
            'accessible_formats_offered' => 'unknown',
            // Provide nested object as per flow chart JSON skeleton
            'art9_precontract_ext' => [
                '1_contract' => [
                    ['hook' => 'coc_acknowledged', 'value' => 'No'],
                ],
                '9_through_ticket' => [
                    ['hook' => 'through_ticket_disclosure', 'value' => 'unknown'],
                ],
            ],
            // Other parts positive
            'info_on_rights' => 'Ja',
            'rights_notice_displayed' => 'Ja',
            'rights_contact_provided' => 'Ja',
            'info_during_disruption' => 'Ja',
            'station_board_updates' => 'Ja',
            'onboard_announcements' => 'Ja',
            'disruption_updates_frequency' => 'Ja',
            'assistance_contact_visible' => 'Ja',
        ];
        $res = $svc->evaluate([], $meta);
        // Nested No should flatten to coc_acknowledged='Nej' and force parent
        $this->assertSame('Nej', $res['hooks']['coc_acknowledged']);
        $this->assertSame('Nej', $res['hooks']['info_before_purchase']);
        $this->assertFalse($res['art9_ok']);
    }
}
