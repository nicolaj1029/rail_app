<?php
declare(strict_types=1);

namespace App\Service\TicketExtraction;

interface ExtractorInterface
{
    public function name(): string;

    /**
     * @return TicketExtractionResult An object with normalized fields and confidence (0..1)
     */
    public function extract(string $text): TicketExtractionResult;
}
