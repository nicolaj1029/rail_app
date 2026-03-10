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
        $session = $this->request->getSession();
        $role = $service->getRole($session);
        $inbox = $service->buildInbox($session);

        $this->set([
            'role' => $role,
            'inbox' => $inbox,
            'roleLocked' => $service->roleIsLocked($session),
            'authUser' => $service->getAuthenticatedUser($session),
            'roleLabel' => $service->getRoleLabel($session),
        ]);
    }

    public function view(): void
    {
        $service = new AdminDeskService();
        $session = $this->request->getSession();
        $role = $service->getRole($session);
        $source = trim((string)$this->request->getQuery('source', 'session'));
        $id = trim((string)$this->request->getQuery('id', $source === 'session' ? 'current' : ''));
        $cockpit = $service->loadDeskItem($session, $source, $id);
        $playbooks = $cockpit !== null ? $service->playbooksForRole($role, $cockpit) : [];

        $this->set([
            'role' => $role,
            'source' => $source,
            'id' => $id,
            'cockpit' => $cockpit,
            'playbooks' => $playbooks,
            'allowedStatuses' => $service->allowedStatusesForRole($role),
            'roleLocked' => $service->roleIsLocked($session),
            'authUser' => $service->getAuthenticatedUser($session),
            'roleLabel' => $service->getRoleLabel($session),
        ]);
    }

    public function role(): Response
    {
        $this->request->allowMethod(['post']);
        $service = new AdminDeskService();
        $session = $this->request->getSession();
        $before = $service->getRole($session);
        $after = $service->setRole($session, (string)$this->request->getData('role'));
        if ($before !== $after) {
            $this->Flash->success('Arbejdstilstand opdateret.');
        } elseif ($service->roleIsLocked($session)) {
            $this->Flash->error('Rollen styres af admin-login og kan ikke ændres her.');
        }

        return $this->redirect((string)$this->request->getData('redirect', '/admin/desk'));
    }

    public function updateStatus(): Response
    {
        $this->request->allowMethod(['post']);
        $service = new AdminDeskService();
        $role = $service->getRole($this->request->getSession());
        $source = trim((string)$this->request->getData('source'));
        $id = trim((string)$this->request->getData('id'));
        $status = trim((string)$this->request->getData('status'));

        if ($source !== '' && $id !== '' && $status !== '') {
            $ok = $service->updateStatusForRole($role, $source, $id, $status);
            if ($ok) {
                $this->Flash->success('Driftstatus opdateret.');
            } else {
                $this->Flash->error('Status kunne ikke opdateres for den aktuelle rolle eller kilde.');
            }
        }

        $redirect = (string)$this->request->getData('redirect', '/admin/desk');

        return $this->redirect($redirect);
    }
}
