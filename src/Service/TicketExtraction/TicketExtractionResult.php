<?php
declare(strict_types=1);

namespace App\Service\TicketExtraction;

final class TicketExtractionResult
{
    /** @var array<string,mixed> */
    public array $fields;
    public float $confidence; // 0..1
    public string $provider;  // heuristics|llm|azure-docint|custom
    /** @var string[] */
    public array $logs;

    /**
     * @param array<string,mixed> $fields
     * @param string[] $logs
     */
    public function __construct(array $fields, float $confidence, string $provider, array $logs = [])
    {
        $this->fields = $fields;
        $this->confidence = max(0.0, min(1.0, $confidence));
        $this->provider = $provider;
        $this->logs = $logs;
    }
}
