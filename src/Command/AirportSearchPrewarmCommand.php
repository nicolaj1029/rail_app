<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\TransportNodeSearchService;
use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;

final class AirportSearchPrewarmCommand extends BaseCommand
{
    protected string $defaultName = 'airport_search_prewarm';

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $startedAt = hrtime(true);
        $service = new TransportNodeSearchService();
        $expected = [
            'Stockholm' => 'ARN',
            'Bruxelles' => 'BRU',
            'Copenhagen' => 'CPH',
            'London' => 'LHR',
            'Paris' => 'CDG',
        ];

        foreach ($expected as $query => $requiredCode) {
            $codes = array_column($service->search('air', $query, null, 8), 'code');
            if (!in_array($requiredCode, $codes, true)) {
                $io->err(sprintf(
                    'Airport search prewarm failed: query "%s" did not return %s.',
                    $query,
                    $requiredCode
                ));

                return static::CODE_ERROR;
            }
        }

        $io->success(sprintf(
            'Airport search cache prewarmed in %.1f ms (%d sentinel queries).',
            (hrtime(true) - $startedAt) / 1_000_000,
            count($expected)
        ));

        return static::CODE_SUCCESS;
    }
}
