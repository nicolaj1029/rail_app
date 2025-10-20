<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;

class ClaimsController extends AppController
{
    public function index(): void
    {
        $claims = $this->fetchTable('Claims')->find()->orderDesc('created')->all();
        $this->set(compact('claims'));
    }

    public function view(string $id): void
    {
        $claim = $this->fetchTable('Claims')->get($id);
        $this->set(compact('claim'));
    }

    public function updateStatus(string $id): void
    {
        $this->request->allowMethod(['post']);
        $claims = $this->fetchTable('Claims');
        $claim = $claims->get($id);
        $claim->status = (string)$this->request->getData('status');
        $claim->notes = (string)($this->request->getData('notes') ?? $claim->notes);
        $claims->save($claim);
        $this->redirect(['action' => 'view', $id]);
        return; // stop execution without returning a Response
    }

    public function markPaid(string $id): void
    {
        $this->request->allowMethod(['post']);
        $claims = $this->fetchTable('Claims');
        $claim = $claims->get($id);
        $claim->payout_status = 'paid';
        $claim->payout_reference = (string)($this->request->getData('payout_reference') ?? '');
        $claim->paid_at = date('Y-m-d H:i:s');
        $claims->save($claim);
        $this->redirect(['action' => 'view', $id]);
        return;
    }
}
