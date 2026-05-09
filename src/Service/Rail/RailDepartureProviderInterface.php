<?php
declare(strict_types=1);

namespace App\Service\Rail;

interface RailDepartureProviderInterface
{
    /**
     * @param array<string,mixed> $criteria
     * @return array<int,array<string,mixed>>
     */
    public function search(array $criteria): array;
}
