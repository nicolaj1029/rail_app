<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AirContractResolver;
use PHPUnit\Framework\TestCase;

final class AirContractResolverTest extends TestCase
{
    public function testProtectedConnectionOnSamePnrKeepsCarrierAsClaimParty(): void
    {
        $result = (new AirContractResolver())->evaluate([
            'contract_topology' => 'protected_single_contract',
            'seller_type' => 'operator',
            'seller_name' => 'SAS',
            'same_pnr' => true,
            'incident_segment_mode' => 'air',
            'incident_segment_operator' => 'SAS',
            'operating_carrier' => 'SAS',
        ], ['regulation_applies' => true]);

        $this->assertSame('protected_connection', $result['air_connection_type']);
        $this->assertSame('carrier', $result['primary_claim_party']);
        $this->assertSame('SAS', $result['primary_claim_party_name']);
    }

    public function testSelfTransferUsesAffectedSegmentOperator(): void
    {
        $result = (new AirContractResolver())->evaluate([
            'contract_topology' => 'separate_contracts',
            'seller_type' => 'operator',
            'seller_name' => 'Ticket Seller',
            'self_transfer_notice' => true,
            'incident_segment_mode' => 'air',
            'incident_segment_operator' => 'Ryanair',
            'operating_carrier' => 'Ryanair',
        ], ['regulation_applies' => true]);

        $this->assertSame('self_transfer', $result['air_connection_type']);
        $this->assertSame('segment_operator', $result['primary_claim_party']);
        $this->assertSame('Ryanair', $result['primary_claim_party_name']);
    }

    public function testUnknownConnectionTypeTriggersManualReview(): void
    {
        $result = (new AirContractResolver())->evaluate([
            'contract_topology' => 'unknown_manual_review',
            'seller_type' => 'unknown',
            'incident_segment_mode' => 'air',
        ], ['regulation_applies' => true]);

        $this->assertTrue($result['manual_review_required']);
    }
}
