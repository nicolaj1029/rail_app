<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class ProvidersController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    public function sncfBookingValidate(): void
    {
        $this->request->allowMethod(['post']);
        $pnr = (string)($this->request->getData('pnr') ?? '');
        $this->set(['pnr' => $pnr, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['pnr','segments']);
    }

    public function sncfTrains(): void
    {
        $date = (string)($this->request->getQuery('date') ?? '');
        $trainNo = (string)($this->request->getQuery('trainNo') ?? '');
        $this->set(['date' => $date, 'trainNo' => $trainNo, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['date','trainNo','segments']);
    }

    public function sncfRealtime(): void
    {
        $trainUid = (string)($this->request->getQuery('trainUid') ?? '');
        $this->set(['trainUid' => $trainUid, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['trainUid','segments']);
    }

    public function dbLookup(): void
    {
        $pnr = (string)($this->request->getQuery('pnr') ?? '');
        $this->set(['pnr' => $pnr, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['pnr','segments']);
    }

    public function dbTrip(): void
    {
        $trainNo = (string)($this->request->getQuery('trainNo') ?? '');
        $date = (string)($this->request->getQuery('date') ?? '');
        $this->set(['trainNo' => $trainNo, 'date' => $date, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['trainNo','date','segments']);
    }

    public function dbRealtime(): void
    {
        $evaId = (string)($this->request->getQuery('evaId') ?? '');
        $time = (string)($this->request->getQuery('time') ?? '');
        $this->set(['evaId' => $evaId, 'time' => $time, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['evaId','time','segments']);
    }

    public function dsbTrip(): void
    {
        $trainNo = (string)($this->request->getQuery('trainNo') ?? '');
        $date = (string)($this->request->getQuery('date') ?? '');
        $this->set(['trainNo' => $trainNo, 'date' => $date, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['trainNo','date','segments']);
    }

    public function dsbRealtime(): void
    {
        $uic = (string)($this->request->getQuery('uic') ?? '');
        $this->set(['uic' => $uic, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['uic','segments']);
    }

    public function rneRealtime(): void
    {
        $trainId = (string)($this->request->getQuery('trainId') ?? '');
        $date = (string)($this->request->getQuery('date') ?? '');
        $this->set(['trainId' => $trainId, 'date' => $date, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['trainId','date','segments']);
    }

    public function openRealtime(): void
    {
        $country = (string)($this->request->getQuery('country') ?? '');
        $trainNo = (string)($this->request->getQuery('trainNo') ?? '');
        $date = (string)($this->request->getQuery('date') ?? '');
        $this->set(['country' => $country, 'trainNo' => $trainNo, 'date' => $date, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['country','trainNo','date','segments']);
    }
}
