<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\OperatorCatalogImportService;
use Cake\Console\{Arguments, BaseCommand, ConsoleIo};

final class OperatorCatalogImportCommand extends BaseCommand
{
    protected string $defaultName = 'operator_catalog_import';

    protected function buildOptionParser(\Cake\Console\ConsoleOptionParser $parser): \Cake\Console\ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->addOptions([
            'catalog' => [
                'help' => 'Optional output catalog path. Defaults to config/data/transport_operators_catalog.json.',
            ],
            'source' => [
                'help' => 'CSV or JSON source file to import.',
            ],
            'format' => [
                'help' => 'auto|csv|json',
            ],
            'template' => [
                'help' => 'Emit a CSV template for a mode and exit.',
                'boolean' => true,
            ],
            'mode' => [
                'help' => 'air|bus|ferry|rail when using --template.',
            ],
            'json' => [
                'help' => 'Output JSON instead of text.',
                'boolean' => true,
            ],
        ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $catalog = trim((string)($args->getOption('catalog') ?? ''));
        $service = new OperatorCatalogImportService($catalog !== '' ? $catalog : null);

        if ((bool)$args->getOption('template')) {
            $mode = strtolower(trim((string)($args->getOption('mode') ?? '')));
            $rows = $service->template($mode);
            $headers = (array)($rows[0]['_headers'] ?? []);
            $io->out(implode(',', $headers));
            $row = (array)($rows[1] ?? []);
            $values = [];
            foreach ($headers as $header) {
                $values[] = (string)($row[$header] ?? '');
            }
            $io->out(implode(',', array_map(static fn(string $v): string => '"' . str_replace('"', '""', $v) . '"', $values)));
            return self::CODE_SUCCESS;
        }

        $source = trim((string)($args->getOption('source') ?? ''));
        if ($source === '') {
            $io->err('Missing --source=<file> or use --template.');
            return self::CODE_ERROR;
        }

        $result = $service->import($source, [
            'format' => (string)($args->getOption('format') ?? 'auto'),
        ]);

        if ((bool)$args->getOption('json')) {
            $io->out(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::CODE_SUCCESS;
        }

        $io->out(sprintf(
            'Imported %s (%s): +%d added, %d updated, total %d',
            $result['source'],
            $result['format'],
            $result['added'],
            $result['updated'],
            $result['total']
        ));

        return self::CODE_SUCCESS;
    }
}
