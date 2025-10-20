<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\OcrHeuristicsMapper;
use Cake\TestSuite\TestCase;

final class OcrHeuristicsMapperTest extends TestCase
{
    public function testFixturesMapCoreFields(): void
    {
        $fixturesDir = __DIR__ . '/../../../mocks/tests/fixtures/';
        $manifest = $fixturesDir . 'ocr_expectations.json';
        $this->assertFileExists($manifest, 'Expectation manifest missing');
        $data = json_decode((string)file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);

        $mapper = new OcrHeuristicsMapper();

        foreach ($data as $file => $expected) {
            $path = $fixturesDir . $file;
            $this->assertFileExists($path, "Fixture missing: $file");
            $text = (string)file_get_contents($path);
            $res = $mapper->mapText($text);
            $auto = $res['auto'] ?? [];

            foreach (['dep_station','arr_station','dep_date','dep_time','arr_time','train_no'] as $key) {
                if (!array_key_exists($key, $expected)) { continue; }
                $exp = $expected[$key];
                $val = $auto[$key]['value'] ?? null;
                $this->assertSame($exp, $val, sprintf('%s mismatch for %s. Logs: %s', $key, $file, implode(' | ', $res['logs'] ?? [])));
            }
        }
    }
}
