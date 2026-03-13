<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\TransportNodeImportService;
use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

final class TransportNodesImportCommand extends BaseCommand
{
    protected string $defaultName = 'transport_nodes_import';

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser
            ->addOption('mode', ['required' => true, 'help' => 'ferry, bus or air'])
            ->addOption('source', ['required' => true, 'help' => 'Path to CSV or JSON source file'])
            ->addOption('format', ['default' => 'auto', 'help' => 'auto, csv or json'])
            ->addOption('replace', ['boolean' => true, 'default' => false, 'help' => 'Replace existing rows for the selected mode'])
            ->addOption('source-label', ['help' => 'Stored source label, e.g. ourairports or unlocode'])
            ->addOption('name-col', ['help' => 'CSV/JSON field for node name'])
            ->addOption('country-col', ['help' => 'CSV/JSON field for country code'])
            ->addOption('code-col', ['help' => 'CSV/JSON field for code'])
            ->addOption('lat-col', ['help' => 'CSV/JSON field for latitude'])
            ->addOption('lon-col', ['help' => 'CSV/JSON field for longitude'])
            ->addOption('node-type-col', ['help' => 'CSV/JSON field for node type'])
            ->addOption('city-col', ['help' => 'CSV/JSON field for city'])
            ->addOption('parent-col', ['help' => 'CSV/JSON field for parent/terminal grouping'])
            ->addOption('aliases-col', ['help' => 'CSV/JSON field for aliases (comma/semicolon/pipe separated)'])
            ->addOption('in-eu-col', ['help' => 'CSV/JSON field for explicit EU flag'])
            ->addOption('delimiter', ['help' => 'CSV delimiter, default comma'])
            ->addOption('default-node-type', ['help' => 'Fallback node type if source lacks it']);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $mode = (string)$args->getOption('mode');
        $source = (string)$args->getOption('source');

        $options = [
            'format' => (string)$args->getOption('format'),
            'replace' => (bool)$args->getOption('replace'),
            'source_label' => (string)($args->getOption('source-label') ?? ''),
            'name_col' => (string)($args->getOption('name-col') ?? ''),
            'country_col' => (string)($args->getOption('country-col') ?? ''),
            'code_col' => (string)($args->getOption('code-col') ?? ''),
            'lat_col' => (string)($args->getOption('lat-col') ?? ''),
            'lon_col' => (string)($args->getOption('lon-col') ?? ''),
            'node_type_col' => (string)($args->getOption('node-type-col') ?? ''),
            'city_col' => (string)($args->getOption('city-col') ?? ''),
            'parent_col' => (string)($args->getOption('parent-col') ?? ''),
            'aliases_col' => (string)($args->getOption('aliases-col') ?? ''),
            'in_eu_col' => (string)($args->getOption('in-eu-col') ?? ''),
            'delimiter' => (string)($args->getOption('delimiter') ?? ''),
            'default_node_type' => (string)($args->getOption('default-node-type') ?? ''),
        ];

        $service = new TransportNodeImportService();
        try {
            $result = $service->import($mode, $source, $options);
        } catch (\Throwable $e) {
            $io->err($e->getMessage());
            return static::CODE_ERROR;
        }

        $io->out(sprintf(
            'Imported %s nodes from %s (added: %d, updated: %d, total: %d)',
            $result['mode'],
            $result['source'],
            $result['added'],
            $result['updated'],
            $result['total']
        ));

        return static::CODE_SUCCESS;
    }
}
