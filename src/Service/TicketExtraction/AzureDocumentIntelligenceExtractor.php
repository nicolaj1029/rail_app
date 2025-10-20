<?php
declare(strict_types=1);

namespace App\Service\TicketExtraction;

final class AzureDocumentIntelligenceExtractor implements ExtractorInterface
{
    public function name(): string { return 'azure-docint'; }

    public function extract(string $text): TicketExtractionResult
    {
        // Stub: In a real setup, send the image/PDF to Azure Document Intelligence for key-value extraction.
        return new TicketExtractionResult([], 0.2, $this->name(), ['Azure Document Intelligence extractor disabled (stub)']);
    }
}
