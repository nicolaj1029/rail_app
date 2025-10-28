<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Art12Evaluator;
use Cake\TestSuite\TestCase;

final class Art12EvaluatorTest extends TestCase
{
    public function testRetailerSingleTxnNoNotice(): void
    {
        $svc = new Art12Evaluator();
        $journey = [
            'segments' => [
                ['pnr'=>'XYZ123','carrier'=>'SNCF'],
                ['pnr'=>'XYZ123','carrier'=>'SNCF'],
            ],
            'bookingRef' => 'XYZ123',
            'seller_type' => 'agency',
        ];
        $meta = [
            'through_ticket_disclosure' => 'Gennemgående',
            'single_txn_retailer'       => 'Ja',
            'separate_contract_notice'  => 'Nej',
        ];
        $out = $svc->evaluate($journey, $meta);
        $this->assertTrue($out['art12_applies']);
        $this->assertSame('agency', $out['liable_party']);
        $this->assertNotEmpty($out['missing_ui']);
        $this->assertIsArray($out['missing_auto']);
    }

    public function testSeparateNoticeOverrides(): void
    {
        $svc = new Art12Evaluator();
        $journey = [
            'segments' => [
                ['pnr'=>'A1','carrier'=>'DB'],
                ['pnr'=>'B2','carrier'=>'DB'],
            ],
            'seller_type' => 'operator',
        ];
        $meta = [
            'through_ticket_disclosure' => 'Særskilte',
            'separate_contract_notice'  => 'Ja',
        ];
        $out = $svc->evaluate($journey, $meta);
        $this->assertFalse($out['art12_applies']);
        $this->assertIsArray($out['missing_ui']);
        $this->assertIsArray($out['missing_auto']);
    }

    // A: single_txn = FALSE -> Ikke gennemgående
    public function testA_SingleTxnFalse_NotThrough(): void
    {
        $svc = new Art12Evaluator();
        $journey = [
            'segments' => [
                ['pnr' => 'A', 'carrier' => 'OP1'],
                ['pnr' => 'B', 'carrier' => 'OP1'],
            ],
            'seller_type' => 'operator',
        ];
        $meta = [
            'single_txn_operator' => 'Nej',
            'single_txn_retailer' => 'Nej',
            'separate_contract_notice' => 'Nej',
        ];
        $out = $svc->evaluate($journey, $meta);
        $this->assertFalse($out['art12_applies']);
    }

    // B: TRUE, OPERATOR, separate_mark FALSE -> Gennemgående
    public function testB_SingleTxnOperator_NoSep_Through(): void
    {
        $svc = new Art12Evaluator();
        $journey = [
            'segments' => [
                ['pnr' => 'XYZ', 'carrier' => 'OP1'],
                ['pnr' => 'XYZ', 'carrier' => 'OP1'],
            ],
            'bookingRef' => 'XYZ',
            'seller_type' => 'operator',
        ];
        $meta = [
            'shared_pnr_scope' => 'Ja',
            'single_booking_reference' => 'Ja',
            'separate_contract_notice' => 'Nej',
        ];
        $out = $svc->evaluate($journey, $meta);
        $this->assertTrue($out['art12_applies']);
    }

    // C: TRUE, OPERATOR, sep TRUE, disclosure FALSE -> Gennemgående
    public function testC_Operator_SepYes_DisclosureNo_Through(): void
    {
        $svc = new Art12Evaluator();
        $journey = [
            'segments' => [
                ['pnr' => 'XYZ', 'carrier' => 'OP1'],
                ['pnr' => 'XYZ', 'carrier' => 'OP1'],
            ],
            'bookingRef' => 'XYZ',
            'seller_type' => 'operator',
        ];
        $meta = [
            'shared_pnr_scope' => 'Ja',
            'single_booking_reference' => 'Ja',
            'separate_contract_notice' => 'Ja',
            'through_ticket_disclosure' => 'Nej',
        ];
        $out = $svc->evaluate($journey, $meta);
        $this->assertTrue($out['art12_applies']);
    }

    // D: TRUE, OPERATOR, sep TRUE, disclosure TRUE -> Ikke gennemgående
    public function testD_Operator_SepYes_DisclosureYes_NotThrough(): void
    {
        $svc = new Art12Evaluator();
        $journey = [
            'segments' => [
                ['pnr' => 'XYZ', 'carrier' => 'OP1'],
                ['pnr' => 'XYZ', 'carrier' => 'OP1'],
            ],
            'bookingRef' => 'XYZ',
            'seller_type' => 'operator',
        ];
        $meta = [
            'shared_pnr_scope' => 'Ja',
            'single_booking_reference' => 'Ja',
            'separate_contract_notice' => 'Ja',
            'through_ticket_disclosure' => 'Ja',
        ];
        $out = $svc->evaluate($journey, $meta);
        $this->assertFalse($out['art12_applies']);
    }

    // E: TRUE, RETAILER, sep FALSE -> Gennemgående (12(4))
    public function testE_Retailer_NoSep_Through(): void
    {
        $svc = new Art12Evaluator();
        $journey = [
            'segments' => [
                ['pnr' => 'XYZ', 'carrier' => 'OP1'],
                ['pnr' => 'XYZ', 'carrier' => 'OP2'],
            ],
            'bookingRef' => 'XYZ',
            'seller_type' => 'agency',
        ];
        $meta = [
            'shared_pnr_scope' => 'Ja',
            'single_booking_reference' => 'Ja',
            'separate_contract_notice' => 'Nej',
            'single_txn_retailer' => 'Ja',
        ];
        $out = $svc->evaluate($journey, $meta);
        $this->assertTrue($out['art12_applies']);
    }

    // F: TRUE, RETAILER, sep TRUE + disclosure TRUE -> Ikke gennemgående
    public function testF_Retailer_SepYes_DisclosureYes_NotThrough(): void
    {
        $svc = new Art12Evaluator();
        $journey = [
            'segments' => [
                ['pnr' => 'XYZ', 'carrier' => 'OP1'],
                ['pnr' => 'XYZ', 'carrier' => 'OP2'],
            ],
            'bookingRef' => 'XYZ',
            'seller_type' => 'agency',
        ];
        $meta = [
            'shared_pnr_scope' => 'Ja',
            'single_booking_reference' => 'Ja',
            'separate_contract_notice' => 'Ja',
            'through_ticket_disclosure' => 'Ja',
            'single_txn_retailer' => 'Ja',
        ];
        $out = $svc->evaluate($journey, $meta);
        $this->assertFalse($out['art12_applies']);
    }
}
