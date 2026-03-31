<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\CodexSourcingPlan;
use Cake\Console\{Arguments, BaseCommand, ConsoleIo};

final class SourcingPlanCommand extends BaseCommand
{
    protected string $defaultName = 'sourcing_plan';

    protected function buildOptionParser(\Cake\Console\ConsoleOptionParser $parser): \Cake\Console\ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->addOptions([
            'tab' => [
                'help' => 'Optional tab name: cross|air|ferry|bus|rail',
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
        $plan = new CodexSourcingPlan();
        $tab = strtolower(trim((string)($args->getOption('tab') ?? '')));
        $asJson = (bool)$args->getOption('json');

        if ($tab !== '') {
            $selected = $plan->tab($tab);
            if ($selected === null) {
                $io->err(sprintf('Unknown tab "%s". Use cross, air, ferry, bus or rail.', $tab));
                return self::CODE_ERROR;
            }

            if ($asJson) {
                $io->out(json_encode([
                    'tab' => $tab,
                    'data' => $selected,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return self::CODE_SUCCESS;
            }

            $io->out(strtoupper($tab) . ': ' . (string)($selected['goal'] ?? ''));
            $io->out('Priority order: ' . implode(', ', (array)($selected['priority_order'] ?? [])));
            $io->out('Tasks: ' . count((array)($selected['tasks'] ?? [])));
            return self::CODE_SUCCESS;
        }

        $summary = $plan->summary();
        if ($asJson) {
            $io->out(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::CODE_SUCCESS;
        }

        $io->out('Codex sourcing plan');
        $io->out('Source: ' . (string)($summary['source'] ?? ''));
        $io->out('Generated: ' . (string)($summary['generated_at'] ?? ''));
        $io->out('');

        foreach ((array)($summary['tabs'] ?? []) as $name => $info) {
            $io->out(sprintf(
                '%s: %d tasks (%d high, %d medium)',
                strtoupper((string)$name),
                (int)($info['task_count'] ?? 0),
                (int)($info['high_priority_tasks'] ?? 0),
                (int)($info['medium_priority_tasks'] ?? 0)
            ));
        }

        return self::CODE_SUCCESS;
    }
}
