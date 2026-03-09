<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use App\Service\AdminDeskService;
use Cake\Http\Response;

final class DeskController extends AppController
{
    public function index(): void
    {
        $service = new AdminDeskService();
        $role = $service->getRole($this->request->getSession());
        $inbox = $service->buildInbox($this->request->getSession());

        $this->set([
            'role' => $role,
            'inbox' => $inbox,
        ]);
    }

    public function view(): void
    {
        $service = new AdminDeskService();
        $role = $service->getRole($this->request->getSession());
        $source = trim((string)$this->request->getQuery('source', 'session'));
        $id = trim((string)$this->request->getQuery('id', $source === 'session' ? 'current' : ''));
        $cockpit = $service->loadDeskItem($this->request->getSession(), $source, $id);

        $this->set([
            'role' => $role,
            'source' => $source,
            'id' => $id,
            'cockpit' => $cockpit,
            'allowedStatuses' => $service->allowedStatusesForRole($role),
        ]);
    }

    public function role(): Response
    {
        $this->request->allowMethod(['post']);
        $service = new AdminDeskService();
        $service->setRole($this->request->getSession(), (string)$this->request->getData('role'));

        return $this->redirect((string)$this->request->getData('redirect', '/admin/desk'));
    }

    public function updateStatus(): Response
    {
        $this->request->allowMethod(['post']);
        $service = new AdminDeskService();
        $source = trim((string)$this->request->getData('source'));
        $id = trim((string)$this->request->getData('id'));
        $status = trim((string)$this->request->getData('status'));

        if ($source !== '' && $id !== '' && $status !== '') {
            $service->updateStatus($source, $id, $status);
        }

        $redirect = (string)$this->request->getData('redirect', '/admin/desk');

        return $this->redirect($redirect);
    }
}
