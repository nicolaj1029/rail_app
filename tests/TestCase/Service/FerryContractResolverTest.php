<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\FerryContractResolver;
use Cake\TestSuite\TestCase;

final class FerryContractResolverTest extends TestCase
{
    public function testSeparateContractsFollowAffectedSegment(): void
    {
        $result = (new FerryContractResolver())->evaluate([
            'contract_topology' => 'separate_contracts',
            'seller_type' => 'operator',
            'seller_name' => 'Combined Seller',
            'incident_segment_mode' => 'ferry',
            'incident_segment_operator' => 'ForSea',
        ], [
            'regulation_applies' => true,
        ]);

        $this->assertSame('segment_operator', $result['primary_claim_party']);
        $this->assertSame('ForSea', $result['primary_claim_party_name']);
        $this->assertSame('ferry', $result['rights_module']);
        $this->assertSame('separate_contracts_follow_affected_segment', $result['decision_basis']);
        $this->assertFalse($result['manual_review_required']);
    }

    public function testSingleMultimodalContractKeepsSellerButUsesAffectedRightsModule(): void
    {
        $result = (new FerryContractResolver())->evaluate([
            'contract_topology' => 'single_multimodal_contract',
            'seller_type' => 'operator',
            'seller_name' => 'Scandlines',
            'incident_segment_mode' => 'rail',
            'incident_segment_operator' => 'DSB',
        ], [
            'regulation_applies' => true,
        ]);

        $this->assertSame('carrier', $result['primary_claim_party']);
        $this->assertSame('Scandlines', $result['primary_claim_party_name']);
        $this->assertSame('rail', $result['rights_module']);
        $this->assertSame('single_contract_direct_carrier', $result['decision_basis']);
    }

    public function testUnknownSegmentTriggersManualReview(): void
    {
        $result = (new FerryContractResolver())->evaluate([
            'contract_topology' => 'single_multimodal_contract',
            'seller_type' => 'ticket_vendor',
            'seller_name' => 'Travel Platform',
            'incident_segment_mode' => 'unknown',
            'incident_segment_operator' => '',
        ], [
            'regulation_applies' => true,
        ]);

        $this->assertTrue($result['manual_review_required']);
        $this->assertSame('manual_review', $result['primary_claim_party']);
        $this->assertSame('unknown_contract_or_segment', $result['decision_basis']);
    }
}
