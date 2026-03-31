<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\CaseRiskService;
use PHPUnit\Framework\TestCase;

final class CaseRiskServiceTest extends TestCase
{
    public function testEvaluateFlagsDuplicateBookingReferenceAndTicketFingerprint(): void
    {
        $ocrText = trim(str_repeat('Ticket evidence line ', 12));
        $fingerprint = sha1(trim((string)preg_replace('/\s+/', ' ', mb_strtolower($ocrText, 'UTF-8'))));

        $result = (new CaseRiskService())->evaluate(
            $this->baseSnapshot([
                'meta' => [
                    '_ocr_text' => $ocrText,
                ],
            ]),
            [[
                'case_ref' => 'CASE-OLD-1',
                'booking_reference' => 'ABC123',
                'ticket_fingerprint' => $fingerprint,
                'passenger_name' => 'John Doe',
            ]]
        );

        $codes = array_column($result['flags'], 'code');

        $this->assertSame('high', $result['level']);
        $this->assertTrue($result['fraud_review_required']);
        $this->assertTrue($result['duplicate_flag']);
        $this->assertContains('duplicate_booking_reference', $codes);
        $this->assertContains('duplicate_booking_reference_different_passenger', $codes);
        $this->assertContains('duplicate_ticket_fingerprint', $codes);
    }

    public function testEvaluateFlagsMissingIncidentLegAndImpossibleTiming(): void
    {
        $result = (new CaseRiskService())->evaluate(
            $this->baseSnapshot([
                'meta' => [
                    '_multimodal' => [
                        'canonical' => [
                            'incident' => ['incident_leg_id' => 'leg_missing'],
                            'legs' => [[
                                'leg_id' => 'leg_1',
                                'mode' => 'rail',
                                'operator' => 'DSB',
                                'origin' => 'Odense',
                                'destination' => 'Kobenhavn H',
                                'planned_departure' => '2026-03-21T11:00',
                                'planned_arrival' => '2026-03-21T10:15',
                                'service_date' => '2026-03-21',
                            ]],
                        ],
                    ],
                ],
            ]),
            [['case_ref' => 'CASE-OTHER']]
        );

        $codes = array_column($result['flags'], 'code');

        $this->assertSame('medium', $result['level']);
        $this->assertFalse($result['fraud_review_required']);
        $this->assertContains('incident_leg_missing', $codes);
        $this->assertContains('impossible_leg_timing', $codes);
    }

    /**
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    private function baseSnapshot(array $override = []): array
    {
        $snapshot = [
            'form' => [
                'passenger_name' => 'Jane Doe',
                'operator' => 'DSB',
            ],
            'journey' => [
                'transport_mode' => 'rail',
            ],
            'meta' => [
                '_multimodal' => [
                    'canonical' => [
                        'transport_mode' => 'rail',
                        'journey' => [
                            'operator' => 'DSB',
                            'origin' => 'Odense',
                            'destination' => 'Kobenhavn H',
                            'ticket_no' => 'ABC123',
                            'service_date' => '2026-03-21',
                        ],
                        'incident' => [
                            'incident_leg_id' => 'leg_1',
                        ],
                        'legs' => [[
                            'leg_id' => 'leg_1',
                            'mode' => 'rail',
                            'operator' => 'DSB',
                            'origin' => 'Odense',
                            'destination' => 'Kobenhavn H',
                            'planned_departure' => '2026-03-21T08:00',
                            'planned_arrival' => '2026-03-21T09:15',
                            'service_date' => '2026-03-21',
                        ]],
                    ],
                ],
            ],
        ];

        return array_replace_recursive($snapshot, $override);
    }
}
