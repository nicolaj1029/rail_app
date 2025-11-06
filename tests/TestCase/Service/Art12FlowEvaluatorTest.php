<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Art12FlowEvaluator;
use Cake\TestSuite\TestCase;

final class Art12FlowEvaluatorTest extends TestCase
{
    public function testStopsThroughSingleOperator(): void
    {
        $svc = new Art12FlowEvaluator();
        $flow = [
            'meta' => [
                'ticket_upload_count' => 2,
                'shared_pnr_scope' => 'yes',
                'journey.seller_type' => 'operator',
            ],
            'journey' => [
                'segments' => [
                    ['operator' => 'DSB'],
                    ['operator' => 'DSB'],
                ]
            ]
        ];
        $res = $svc->decide($flow);
        $this->assertSame('STOP', $res['stage']);
        $this->assertSame('through', $res['ticket_scope']);
        $this->assertSame(Art12FlowEvaluator::RESP_ART12_3_OPERATOR, $res['responsibility']);
    }

    public function testNeedsDisclosureWhenRetailer(): void
    {
        $svc = new Art12FlowEvaluator();
        $flow = [
            'meta' => [
                'ticket_upload_count' => 2,
                'shared_pnr_scope' => 'yes',
                'journey.seller_type' => 'retailer',
            ],
            'journey' => [
                'segments' => [
                    ['operator' => 'DSB'],
                    ['operator' => 'SJ'],
                ]
            ]
        ];
        $res = $svc->decide($flow);
        $this->assertSame('TRIN_4', $res['stage']);
        $this->assertContains('through_ticket_disclosure_given', $res['hooks_to_collect']);
    }

    public function testSeparateContractsWhenExplicitlyDisclosed(): void
    {
        $svc = new Art12FlowEvaluator();
        $flow = [
            'meta' => [
                'ticket_upload_count' => 2,
                'shared_pnr_scope' => 'yes',
                'journey.seller_type' => 'operator',
                'through_ticket_disclosure_given' => 'yes',
                'separate_contract_notice' => 'yes',
            ],
            'journey' => [
                'segments' => [
                    ['operator' => 'DSB'],
                    ['operator' => 'SJ'],
                ]
            ]
        ];
        $res = $svc->decide($flow);
        $this->assertSame('STOP', $res['stage']);
        $this->assertSame('separate', $res['ticket_scope']);
        $this->assertSame(Art12FlowEvaluator::RESP_NONE, $res['responsibility']);
    }
}
