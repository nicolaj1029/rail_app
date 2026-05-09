<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use App\Service\AdminDeskService;
use App\Service\Rail\RailTransportServiceClient;
use App\Service\Rail\RailTransportServiceManager;
use Cake\Http\Response;

final class DeskController extends AppController
{
    public function index(): void
    {
        $service = new AdminDeskService();
        $session = $this->request->getSession();
        $service->ensureSessionCase($session);
        $role = $service->getRole($session);
        $filter = trim((string)$this->request->getQuery('filter', 'all'));
        $search = trim((string)$this->request->getQuery('q', ''));
        $inbox = $service->buildInbox($session, $filter, $search);
        $railTransport = $this->buildRailTransportStatus();

        $this->set([
            'role' => $role,
            'inbox' => $inbox,
            'filter' => (string)($inbox['filter'] ?? 'all'),
            'search' => (string)($inbox['search'] ?? ''),
            'roleLocked' => $service->roleIsLocked($session),
            'authUser' => $service->getAuthenticatedUser($session),
            'roleLabel' => $service->getRoleLabel($session),
            'railTransport' => $railTransport,
        ]);
    }

    public function view(): void
    {
        $service = new AdminDeskService();
        $session = $this->request->getSession();
        $service->ensureSessionCase($session);
        $role = $service->getRole($session);
        $source = trim((string)$this->request->getQuery('source', 'session'));
        $id = trim((string)$this->request->getQuery('id', $source === 'session' ? 'current' : ''));
        $ref = trim((string)$this->request->getQuery('ref', ''));
        $cockpit = $service->loadDeskItem($session, $source, $id);
        if ($cockpit === null && $source === 'case' && $ref !== '') {
            $cockpit = $service->loadDeskItem($session, $source, $ref);
            if ($cockpit !== null) {
                $id = $ref;
            }
        }
        $playbooks = $cockpit !== null ? $service->playbooksForRole($role, $cockpit) : [];

        $this->set([
            'role' => $role,
            'source' => $source,
            'id' => $id,
            'ref' => $ref,
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
        $session = $this->request->getSession();
        $role = $service->getRole($session);
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

    public function note(): Response
    {
        $this->request->allowMethod(['post']);
        $service = new AdminDeskService();
        $session = $this->request->getSession();
        $ok = $service->addNote(
            trim((string)$this->request->getData('source')),
            trim((string)$this->request->getData('id')),
            $service->getRole($session),
            $service->getAuthenticatedUser($session),
            trim((string)$this->request->getData('note'))
        );

        if ($ok) {
            $this->Flash->success('Intern note gemt.');
        } else {
            $this->Flash->error('Kunne ikke gemme note.');
        }

        return $this->redirect((string)$this->request->getData('redirect', '/admin/desk'));
    }

    public function followUp(): Response
    {
        $this->request->allowMethod(['post']);
        $service = new AdminDeskService();
        $session = $this->request->getSession();
        $ok = $service->saveFollowUp(
            trim((string)$this->request->getData('source')),
            trim((string)$this->request->getData('id')),
            $service->getRole($session),
            $service->getAuthenticatedUser($session),
            trim((string)$this->request->getData('follow_up_at')),
            trim((string)$this->request->getData('follow_up_reason'))
        );

        if ($ok) {
            $this->Flash->success('Opfølgning gemt.');
        } else {
            $this->Flash->error('Kunne ikke gemme opfølgning.');
        }

        return $this->redirect((string)$this->request->getData('redirect', '/admin/desk'));
    }

    public function startRailTransport(): Response
    {
        $this->request->allowMethod(['post']);
        $result = (new RailTransportServiceManager())->start();
        if (!empty($result['ok'])) {
            $this->Flash->success((string)($result['message'] ?? 'Rail transport service startet.'));
        } else {
            $this->Flash->error((string)($result['message'] ?? 'Kunne ikke starte rail transport service.'));
        }

        return $this->redirect((string)$this->request->getData('redirect', '/admin/desk'));
    }

    public function stopRailTransport(): Response
    {
        $this->request->allowMethod(['post']);
        $result = (new RailTransportServiceManager())->stop();
        if (!empty($result['ok'])) {
            $this->Flash->success((string)($result['message'] ?? 'Rail transport service stoppet.'));
        } else {
            $this->Flash->error((string)($result['message'] ?? 'Kunne ikke stoppe rail transport service.'));
        }

        return $this->redirect((string)$this->request->getData('redirect', '/admin/desk'));
    }

    /**
     * @return array<string,mixed>
     */
    private function buildRailTransportStatus(): array
    {
        $client = new RailTransportServiceClient();
        $managerStatus = (new RailTransportServiceManager())->status();
        $enabled = $client->isConfigured();
        $status = [
            'enabled' => $enabled,
            'configured' => $enabled,
            'ok' => false,
            'service' => 'rail-transport-service',
            'base_url' => $enabled ? (string)\Cake\Core\Configure::read('Rail.transportServiceBaseUrl', '') : '',
            'provider_order' => [],
            'diagnostics' => ['providers' => [], 'warnings' => [], 'errors' => []],
            'manager' => $managerStatus,
        ];

        if (!$enabled) {
            return $status + [
                'message' => 'Rail transport service er ikke aktiveret i Cake-config.',
            ];
        }

        $health = $client->health();
        if ($health === []) {
            return $status + [
                'message' => 'Ingen kontakt til rail-transport-service.',
            ];
        }

        return [
            'enabled' => true,
            'configured' => true,
            'ok' => (bool)($health['ok'] ?? false),
            'service' => (string)($health['service'] ?? 'rail-transport-service'),
            'base_url' => $status['base_url'],
            'provider_order' => (array)($health['provider_order'] ?? []),
            'diagnostics' => (array)($health['diagnostics'] ?? ['providers' => [], 'warnings' => [], 'errors' => []]),
            'config' => (array)($health['config'] ?? []),
            'manager' => $managerStatus,
            'message' => (bool)($health['ok'] ?? false)
                ? 'Rail transport service svarer.'
                : 'Rail transport service svarer, men health probe er ikke grøn.',
        ];
    }
}
