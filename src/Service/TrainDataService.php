<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Client;
use Cake\Core\Configure;

class TrainDataService
{
    private Client $http;
    private string $dbBase;
    private string $sncfBase;
    private ?string $sncfKey;
    private bool $useLive;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client(['timeout' => 6]);
        $this->dbBase = rtrim((string)Configure::read('External.dbTransportRestBase', 'https://v6.db.transport.rest'), '/');
        $this->sncfBase = rtrim((string)Configure::read('External.sncfNavitiaBase', 'https://api.sncf.com'), '/');
        $this->sncfKey = Configure::read('External.sncfApiKey') ?: null;
        $this->useLive = (bool)Configure::read('External.useLiveApis', false);
    }

    /**
     * Try to get delay minutes for a train using operator hint.
     * Returns null when live is disabled or data unavailable.
     */
    public function getDelayMinutes(array $journey): ?int
    {
        if (!$this->useLive) {
            return null; // feature disabled
        }
        $segments = (array)($journey['segments'] ?? []);
        if (empty($segments)) { return null; }
        $last = $segments[array_key_last($segments)];
        $operator = (string)($last['operator'] ?? ($journey['operator'] ?? ''));
        $trainNo = (string)($last['trainNo'] ?? ($journey['trainNo'] ?? ''));
        $from = (string)($last['from'] ?? '');
        $to = (string)($last['to'] ?? '');
        $date = (string)($journey['depDate']['value'] ?? ($last['date'] ?? ''));
        $when = (string)($last['schedDep'] ?? ($journey['schedDepTime']['value'] ?? ''));

        // Normalize date-time into ISO when if possible
        $whenIso = null;
        if ($date && $when && preg_match('/^\d{2}:\d{2}/', $when)) {
            $whenIso = $date . 'T' . substr($when, 0, 5);
        }

        $op = strtolower($operator);
        try {
            if (str_contains($op, 'db') || str_contains($op, 'bahn') || preg_match('/^ice|ic|re|rb/i', $trainNo)) {
                return $this->queryDbTransportRest($trainNo, $from, $to, $whenIso);
            }
            if (str_contains($op, 'sncf') || str_contains($op, 'tgv') || str_contains($op, 'ter')) {
                return $this->querySncf($trainNo, $from, $to, $whenIso);
            }
        } catch (\Throwable $e) {
            // swallow and return null
        }
        return null;
    }

    /**
     * Use transport.rest to get a trip and compute delay of arrival.
     */
    private function queryDbTransportRest(string $trainNo, string $from, string $to, ?string $whenIso): ?int
    {
        // Simplified: use /trips?query=ICE%20123&when=... and pick arrival delay
        $params = ['query' => $trainNo];
        if ($whenIso) { $params['when'] = $whenIso; }
        $res = $this->http->get($this->dbBase . '/trips', $params);
        if (!$res->isOk()) { return null; }
        $json = $res->getJson();
        if (!is_array($json)) { return null; }
        // transport.rest v6 returns { trips: [ { legs:[ { arrival: { when, plannedWhen, delay } } ] } ] }
        $trips = $json['trips'] ?? [];
        if (!is_array($trips) || empty($trips)) { return null; }
        $bestDelay = 0;
        foreach ($trips as $trip) {
            $legs = $trip['legs'] ?? [];
            if (!is_array($legs) || empty($legs)) { continue; }
            $lastLeg = $legs[array_key_last($legs)];
            $arr = $lastLeg['arrival'] ?? [];
            $delay = $arr['delay'] ?? null;
            if (is_int($delay)) {
                $bestDelay = max($bestDelay, (int)round($delay / 60)); // delay often in seconds
            } else {
                $when = $arr['when'] ?? null;
                $planned = $arr['plannedWhen'] ?? null;
                if ($when && $planned) {
                    $t1 = strtotime($planned); $t2 = strtotime($when);
                    if ($t1 && $t2) { $bestDelay = max($bestDelay, (int)round(($t2 - $t1)/60)); }
                }
            }
        }
        return $bestDelay > 0 ? $bestDelay : 0;
    }

    /**
     * Use SNCF API (Navitia) to compute arrival delay in minutes.
     */
    private function querySncf(string $trainNo, string $from, string $to, ?string $whenIso): ?int
    {
        // Minimal: require API key, otherwise return null
        if (!$this->sncfKey) { return null; }
        // Navitia style query could be /coverage/fr-idf/journeys?from=...&to=...&datetime=...
        $headers = ['Authorization' => $this->sncfKey];
        $params = [];
        if ($from) { $params['from'] = $from; }
        if ($to) { $params['to'] = $to; }
        if ($whenIso) { $params['datetime'] = str_replace([':', '-'], '', substr($whenIso, 0, 16)) . '00'; }
        $res = $this->http->get($this->sncfBase . '/coverage/sncf/journeys', $params, ['headers' => $headers]);
        if (!$res->isOk()) { return null; }
        $json = $res->getJson();
        if (!is_array($json)) { return null; }
        $journeys = $json['journeys'] ?? [];
        $bestDelay = 0;
        foreach ($journeys as $j) {
            $arr = $j['arrival_date_time'] ?? null; // format YYYYMMDDThhmmss
            $dep = $j['departure_date_time'] ?? null;
            // Navitia often has disruptions/stop_time updates; for MVP, approximate via durations
            $dur = $j['duration'] ?? null; // seconds
            $durBase = $j['durations']['base'] ?? null; // if available
            if (is_int($dur) && is_int($durBase) && $dur > $durBase) {
                $mins = (int)round(($dur - $durBase)/60);
                $bestDelay = max($bestDelay, $mins);
            }
        }
        return $bestDelay > 0 ? $bestDelay : 0;
    }
}
