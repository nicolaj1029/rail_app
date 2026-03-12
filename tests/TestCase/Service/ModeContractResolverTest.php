<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\ModeContractResolver;
use PHPUnit\Framework\TestCase;

final class ModeContractResolverTest extends TestCase
{
    public function testAirProtectedConnectionKeepsSellerButUsesAirRightsModule(): void
    {
        $result = (new ModeContractResolver())->evaluate('air', [
            'contract_topology' => 'protected_single_contract',
            'seller_type' => 'operator',
            'seller_name' => 'SAS',
            'incident_segment_mode' => 'air',
            'incident_segment_operator' => 'SAS',
        ]);

        $this->assertSame('carrier', $result['primary_claim_party']);
        $this->assertSame('SAS', $result['primary_claim_party_name']);
        $this->assertSame('air', $result['rights_module']);
        $this->assertFalse($result['manual_review_required']);
    }

    public function testBusSeparateContractsFollowAffectedSegment(): void
    {
        $result = (new ModeContractResolver())->evaluate('bus', [
            'contract_topology' => 'separate_contracts',
            'seller_type' => 'ticket_vendor',
            'seller_name' => 'Travel Platform',
            'incident_segment_mode' => 'bus',
            'incident_segment_operator' => 'FlixBus',
        ]);

        $this->assertSame('segment_operator', $result['primary_claim_party']);
        $this->assertSame('FlixBus', $result['primary_claim_party_name']);
        $this->assertSame('bus', $result['rights_module']);
        $this->assertFalse($result['manual_review_required']);
    }
}
