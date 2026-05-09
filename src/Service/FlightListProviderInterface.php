<?php
declare(strict_types=1);

namespace App\Service;

interface FlightListProviderInterface
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public function searchByRouteAndDate(string $fromIata, string $toIata, string $date, array $context = []): array;
}
