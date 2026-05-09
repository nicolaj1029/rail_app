<?php
declare(strict_types=1);

namespace App\Service;

interface FerryDepartureListProviderInterface
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public function searchByRouteAndDate(string $departureCode, string $arrivalCode, string $date, array $context = []): array;
}
