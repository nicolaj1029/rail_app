<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\OperatorCatalogImportService;
use Cake\TestSuite\TestCase;

final class OperatorCatalogImportServiceTest extends TestCase
{
    public function testImportsCsvIntoTargetCatalogCopy(): void
    {
        $tmpDir = TMP;
        $catalog = $tmpDir . 'operator_catalog_import_test.json';
        $source = $tmpDir . 'operator_catalog_import_source.csv';

        copy(CONFIG . 'data' . DIRECTORY_SEPARATOR . 'transport_operators_catalog.json', $catalog);

        $csv = implode("\n", [
            'operator_key,mode,display_name,country_code,brand_group,legal_entity_name,operating_carrier_name,aliases,products,service_type,claim_url,support_url,source_urls,source_confidence,codes_json,routes_json,ports_json',
            'TempAir,air,Temp Air,DK,Temp,Temp Air ApS,Temp Air ApS,"temp air|ta","Temp Flex",,"https://example.com/claim",,"https://example.com/src","high","{""iata"":""TA"",""icao"":""TMP""}",,',
        ]) . "\n";
        file_put_contents($source, $csv);

        $svc = new OperatorCatalogImportService($catalog);
        $out = $svc->import($source, ['format' => 'csv']);

        self::assertSame(1, $out['added']);
        self::assertSame(0, $out['updated']);
        self::assertSame('csv', $out['format']);

        $json = json_decode((string)file_get_contents($catalog), true);
        self::assertIsArray($json);
        $found = array_values(array_filter(
            (array)($json['operators'] ?? []),
            static fn(array $op): bool => ($op['operator_key'] ?? '') === 'TempAir'
        ));
        self::assertCount(1, $found);
        self::assertSame('TA', $found[0]['codes']['iata'] ?? null);
        self::assertSame('TMP', $found[0]['codes']['icao'] ?? null);
        self::assertSame('Temp Air', $found[0]['display_name'] ?? null);

        @unlink($catalog);
        @unlink($source);
    }
}
