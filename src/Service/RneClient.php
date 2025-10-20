<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Client;

class RneClient
{
    private string $baseUrl;
    private Client $http;

    public function __construct(?string $baseUrl = null, ?Client $http = null)
    {
        // Default to local mock server, can be overridden via env RNE_BASE_URL
        $this->baseUrl = rtrim($baseUrl ?? (getenv('RNE_BASE_URL') ?: 'http://localhost:5555/api/providers/rne'), '/');
        $this->http = $http ?? new Client(['timeout' => 3]);
    }

    /**
     * Fetch realtime payload for a given train id and service date (YYYY-MM-DD).
     * Returns [] on failure.
     *
     * @return array<string,mixed>
     */
    public function realtime(string $trainId, string $date): array
    {
        try {
            $url = $this->baseUrl . '/realtime';
            $res = $this->http->get($url, ['trainId' => $trainId, 'date' => $date]);
            if (!$res->isOk()) { return []; }
            $json = $res->getJson();
            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
