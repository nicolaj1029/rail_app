<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AirportSearchDatasetIntegrity;
use App\Service\TransportDataPaths;
use App\Service\TransportNodeSearchIndexBuilder;
use Cake\TestSuite\TestCase;
use RuntimeException;

final class AirportSearchDatasetIntegrityTest extends TestCase
{
    /** @var array<int,string> */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
        parent::tearDown();
    }

    public function testCanonicalAirportIndexPassesIntegrityValidation(): void
    {
        $rows = json_decode(
            (string)file_get_contents(TransportDataPaths::transportNodesSearch('air')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertGreaterThanOrEqual(AirportSearchDatasetIntegrity::MIN_AIRPORT_COUNT, count($rows));
        $this->assertSame([], AirportSearchDatasetIntegrity::errors($rows));
        $this->assertNull(AirportSearchDatasetIntegrity::preflightFile(TransportDataPaths::transportNodesSearch('air')));
    }

    public function testEmptyDatasetFailsBeforeReplacingAnExistingIndex(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'airport_empty_source_');
        $this->assertIsString($source);
        file_put_contents($source, "[]\n");

        $outputDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'airport_guard_' . uniqid('', true);
        mkdir($outputDir, 0775, true);
        $this->tempDirs[] = $outputDir;
        $existing = $outputDir . DIRECTORY_SEPARATOR . 'air.json';
        file_put_contents($existing, "preserve-me\n");

        try {
            (new TransportNodeSearchIndexBuilder())->build($source, $outputDir, true);
            $this->fail('Expected an empty generated airport index to fail integrity validation.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('airport count 0', $e->getMessage());
            $this->assertStringContainsString('required sentinel airport is missing: BRU', $e->getMessage());
        } finally {
            @unlink($source);
        }

        $this->assertSame("preserve-me\n", file_get_contents($existing));
    }

    public function testCanonicalGeneratorIsDeterministicAndKeepsAllModeIndexes(): void
    {
        $outputDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'transport_index_' . uniqid('', true);
        mkdir($outputDir, 0775, true);
        $this->tempDirs[] = $outputDir;

        $counts = (new TransportNodeSearchIndexBuilder())->build(
            TransportDataPaths::transportNodes(),
            $outputDir,
            true
        );

        $this->assertGreaterThan(5000, $counts['air']);
        foreach (['air', 'bus', 'ferry'] as $mode) {
            $this->assertSame(
                $this->normalizedFileHash(TransportDataPaths::transportNodesSearch($mode)),
                $this->normalizedFileHash($outputDir . DIRECTORY_SEPARATOR . $mode . '.json'),
                'Generated ' . $mode . ' index must match the committed canonical artifact.'
            );
        }
    }

    public function testTinyFileProducesExplicitRuntimeDiagnostic(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'airport_tiny_');
        $this->assertIsString($path);
        file_put_contents($path, "[]\n");

        $error = AirportSearchDatasetIntegrity::preflightFile($path);
        @unlink($path);

        $this->assertNotNull($error);
        $this->assertStringContainsString('empty or truncated', $error);
        $this->assertStringContainsString('bytes, minimum', $error);
    }

    private function normalizedFileHash(string $path): string
    {
        $contents = (string)file_get_contents($path);

        return hash('sha256', str_replace("\r\n", "\n", $contents));
    }
}
