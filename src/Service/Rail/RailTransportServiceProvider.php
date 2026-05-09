<?php
declare(strict_types=1);

namespace App\Service\Rail;

final class RailTransportServiceProvider implements RailDepartureProviderInterface
{
    public function __construct(
        private ?RailTransportServiceClient $client = null,
        private ?RailDepartureNormalizer $normalizer = null
    ) {
        $this->client ??= new RailTransportServiceClient();
        $this->normalizer ??= new RailDepartureNormalizer();
    }

    /**
     * @param array<string,mixed> $criteria
     * @return array<int,array<string,mixed>>
     */
    public function search(array $criteria): array
    {
        if (!$this->client->isConfigured()) {
            return [];
        }

        $items = $this->client->searchJourneys($criteria);
        $out = [];
        foreach ($items as $item) {
            $out[] = $this->normalizer->normalize($item);
        }

        return $out;
    }
}
