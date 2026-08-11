<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\FlightListProviderDiagnosticsInterface;
use App\Service\FlightListProviderInterface;

final class AirTestProvider implements FlightListProviderInterface, FlightListProviderDiagnosticsInterface
{
    public int $calls = 0;

    /** @param array<int,array<string,mixed>> $items */
    public function __construct(
        private string $name,
        private string $status,
        private array $items,
        private int $delayMicroseconds = 0,
    ) {
    }

    /** Return the configured fixture rows. */
    public function searchByRouteAndDate(string $fromIata, string $toIata, string $date, array $context = []): array
    {
        $this->calls++;
        if ($this->delayMicroseconds > 0) {
            usleep($this->delayMicroseconds);
        }

        return $this->items;
    }

    /** Return the fixture provider name. */
    public function getProviderName(): string
    {
        return $this->name;
    }

    /** Return the fixture provider outcome. */
    public function getLastOutcome(): array
    {
        return [
            'provider' => $this->name,
            'status' => $this->status,
            'latency_ms' => 0.1,
            'item_count' => count($this->items),
        ];
    }
}
