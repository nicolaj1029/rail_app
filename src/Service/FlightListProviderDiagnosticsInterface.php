<?php
declare(strict_types=1);

namespace App\Service;

interface FlightListProviderDiagnosticsInterface
{
    /** Return the stable provider identifier. */
    public function getProviderName(): string;

    /**
     * @return array<string,mixed>
     */
    public function getLastOutcome(): array;
}
