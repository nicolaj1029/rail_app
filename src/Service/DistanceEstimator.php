<?php
declare(strict_types=1);

namespace App\Service;

class DistanceEstimator
{
    private StationGeocoder $geo;

    public function __construct(?StationGeocoder $geo = null)
    {
        $this->geo = $geo ?: new StationGeocoder();
    }

    /** Estimate great-circle distance in km between two stations. */
    public function kmBetweenStations(string $from, string $to): ?float
    {
        $a = $this->geo->lookup($from);
        $b = $this->geo->lookup($to);
        if (!$a || !$b) { return null; }
        return $this->haversineKm($a['lat'], $a['lon'], $b['lat'], $b['lon']);
    }

    /** Optionally sum distances for parsed segments when available. */
    public function sumSegmentsKm(array $segments): ?float
    {
        $sum = 0.0; $have = false;
        foreach ($segments as $s) {
            $from = (string)($s['from'] ?? '');
            $to = (string)($s['to'] ?? '');
            if ($from === '' || $to === '') { continue; }
            $d = $this->kmBetweenStations($from, $to);
            if ($d !== null) { $sum += $d; $have = true; }
        }
        return $have ? $sum : null;
    }

    /** Fill journey.distance_km if missing and dep/arr known. */
    public function populateJourneyDistance(array &$journey, array $meta, array $form): void
    {
        if (isset($journey['distance_km']) && is_numeric($journey['distance_km'])) { return; }
        $dep = (string)($meta['_auto']['dep_station']['value'] ?? ($form['dep_station'] ?? ''));
        $arr = (string)($meta['_auto']['arr_station']['value'] ?? ($form['arr_station'] ?? ''));
        $dist = null;
        // Prefer summing OCR-discovered segments if available
        $segs = (array)($meta['_segments_auto'] ?? []);
        if (!empty($segs)) { $dist = $this->sumSegmentsKm($segs); }
        if ($dist === null && $dep !== '' && $arr !== '') { $dist = $this->kmBetweenStations($dep, $arr); }
        if ($dist !== null) { $journey['distance_km'] = round($dist, 1); }
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371.0; // km
        $dLat = deg2rad($lat2 - $lat1); $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }
}
