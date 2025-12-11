<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Http\Exception\BadRequestException;

class StationsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * GET /api/stations
     * Optional query: bbox=minLat,minLon,maxLat,maxLon (to filter)
     */
    public function index()
    {
        $this->request->allowMethod(['get']);

        // Simple static list for now; later replace with DB/provider.
        $stations = [
            [
                'id' => 'kbh',
                'name' => 'København H',
                'lat' => 55.672826,
                'lon' => 12.564588,
                'radius_m' => 120,
            ],
            [
                'id' => 'nyh',
                'name' => 'Nørreport',
                'lat' => 55.683693,
                'lon' => 12.571561,
                'radius_m' => 100,
            ],
            [
                'id' => 'val',
                'name' => 'Valby',
                'lat' => 55.658330,
                'lon' => 12.507870,
                'radius_m' => 100,
            ],
        ];

        $bbox = $this->request->getQuery('bbox');
        if ($bbox) {
            $parts = array_map('trim', explode(',', (string)$bbox));
            if (count($parts) !== 4) {
                throw new BadRequestException('bbox must be minLat,minLon,maxLat,maxLon');
            }
            [$minLat, $minLon, $maxLat, $maxLon] = array_map('floatval', $parts);
            $stations = array_values(array_filter($stations, function ($s) use ($minLat, $minLon, $maxLat, $maxLon) {
                return $s['lat'] >= $minLat && $s['lat'] <= $maxLat && $s['lon'] >= $minLon && $s['lon'] <= $maxLon;
            }));
        }

        $this->set([
            'success' => true,
            'data' => [
                'stations' => $stations,
            ],
        ]);
    }
}
