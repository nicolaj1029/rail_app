<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class IngestControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testTicketOcrAutoHooks(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $text = "Cheapest fare shown. Alternatives shown. MCT respected. Wifi and Quiet zone. Train: ICE 123";
        $this->post('/api/ingest/ticket', ['text' => $text]);
        $this->assertResponseOk();
        $res = json_decode((string)$this->_response->getBody(), true) ?: [];
        $meta = (array)($res['meta'] ?? []);
        $auto = (array)($meta['_auto'] ?? []);
        $this->assertSame('Ja', $auto['cheapest_highlighted']['value'] ?? null);
        $this->assertSame('Ja', $auto['multiple_fares_shown']['value'] ?? null);
        $this->assertSame('Ja', $auto['alts_shown_precontract']['value'] ?? null);
        $this->assertSame('Ja', $auto['mct_realistic']['value'] ?? null);
        $this->assertSame(['Wifi','Quiet zone'], $auto['promised_facilities']['value'] ?? null);
        $this->assertSame('Kun specifikt tog', $auto['train_specificity']['value'] ?? null);
    }
}
