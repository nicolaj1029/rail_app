<?php
declare(strict_types=1);

namespace App\Service\Rail;

use Cake\Core\Configure;

final class RneTisRailDepartureProvider implements RailDepartureProviderInterface
{
    public function search(array $criteria): array
    {
        $enabled = (bool)Configure::read('Rail.rneTis.enabled', false);
        $baseUrl = trim((string)Configure::read('Rail.rneTis.baseUrl', ''));
        $apiKey = trim((string)Configure::read('Rail.rneTis.apiKey', ''));
        if (!$enabled || $baseUrl === '' || $apiKey === '') {
            return [];
        }

        // Stub only. RNE TIS typically requires bilateral access and is not a public bootstrap API.
        return [];
    }
}
