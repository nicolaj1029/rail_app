<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function Cake\Core\env;

class AdminBasicAuthMiddleware implements MiddlewareInterface
{
    /** Protect enabled admin routes and attach the authenticated role identity. */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->isEnabled()) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        if (str_starts_with($path, '/admin')) {
            $credentials = $this->extractCredentials($request);
            if ($credentials === null) {
                return $this->deny();
            }
            [$u, $p] = $credentials;
            $identity = $this->resolveIdentity($u, $p);
            if ($identity === null) {
                return $this->deny();
            }

            $request = $request
                ->withAttribute('adminUser', $identity['username'])
                ->withAttribute('adminRole', $identity['role'])
                ->withAttribute('adminLabel', $identity['label']);
            $this->persistIdentity($request->getSession(), $identity);
        }

        return $handler->handle($request);
    }

    /** Return whether application-level admin authentication is enabled. */
    private function isEnabled(): bool
    {
        $cfg = (array)Configure::read('AdminAuth');

        return !array_key_exists('enabled', $cfg) || (bool)$cfg['enabled'];
    }

    /**
     * @return array{username:string,role:string,label:string}|null
     */
    private function resolveIdentity(string $username, string $password): ?array
    {
        foreach ($this->configuredUsers() as $user) {
            if (!hash_equals($user['username'], $username)) {
                continue;
            }
            if (!hash_equals($user['password'], $password)) {
                continue;
            }

            return $user;
        }

        return null;
    }

    /**
     * @return list<array{username:string,password:string,role:string,label:string}>
     */
    private function configuredUsers(): array
    {
        $users = [];
        $configured = Configure::read('AdminUsers');
        if (is_array($configured) && $configured !== []) {
            foreach ($configured as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $username = trim((string)($row['username'] ?? ''));
                $password = (string)($row['password'] ?? '');
                if ($username === '' || $password === '') {
                    continue;
                }
                $role = strtolower(trim((string)($row['role'] ?? 'jurist')));
                if (!in_array($role, ['jurist', 'operator'], true)) {
                    $role = 'jurist';
                }
                $users = $this->appendUniqueUser($users, [
                    'username' => $username,
                    'password' => $password,
                    'role' => $role,
                    'label' => trim((string)($row['label'] ?? $username)) ?: $username,
                ]);
            }
        }

        $cfg = (array)Configure::read('AdminAuth');
        $juristUser = trim((string)env('ADMIN_JURIST_USER', 'jurist'));
        $juristPass = (string)env('ADMIN_JURIST_PASS', '');
        $operatorUser = trim((string)env('ADMIN_OPERATOR_USER', 'operator'));
        $operatorPass = (string)env('ADMIN_OPERATOR_PASS', '');
        if ($juristPass !== '' || $operatorPass !== '') {
            if ($juristPass !== '') {
                $users = $this->appendUniqueUser($users, [
                    'username' => $juristUser !== '' ? $juristUser : 'jurist',
                    'password' => $juristPass,
                    'role' => 'jurist',
                    'label' => 'Jurist',
                ]);
            }
            if ($operatorPass !== '') {
                $users = $this->appendUniqueUser($users, [
                    'username' => $operatorUser !== '' ? $operatorUser : 'operator',
                    'password' => $operatorPass,
                    'role' => 'operator',
                    'label' => 'Operator',
                ]);
            }
        }

        $legacyPassword = (string)($cfg['password'] ?? '');
        if ($legacyPassword !== '' && !hash_equals('changeme', $legacyPassword)) {
            $users = $this->appendUniqueUser($users, [
                'username' => trim((string)($cfg['username'] ?? 'admin')) ?: 'admin',
                'password' => $legacyPassword,
                'role' => 'jurist',
                'label' => 'Admin',
            ]);
        }

        return $users;
    }

    /** @return array{0:string,1:string}|null */
    private function extractCredentials(ServerRequestInterface $request): ?array
    {
        $server = $request->getServerParams();
        $phpAuthUser = (string)($server['PHP_AUTH_USER'] ?? '');
        if ($phpAuthUser !== '') {
            return [$phpAuthUser, (string)($server['PHP_AUTH_PW'] ?? '')];
        }

        foreach (
            [
                $request->getHeaderLine('Authorization'),
                (string)($server['HTTP_AUTHORIZATION'] ?? ''),
                (string)($server['REDIRECT_HTTP_AUTHORIZATION'] ?? ''),
            ] as $header
        ) {
            if (!preg_match('/^Basic\s+(.*)$/i', $header, $matches)) {
                continue;
            }
            $decoded = base64_decode($matches[1] ?? '', true) ?: '';
            if (str_contains($decoded, ':')) {
                return explode(':', $decoded, 2);
            }
        }

        return null;
    }

    /**
     * @param list<array{username:string,password:string,role:string,label:string}> $users
     * @param array{username:string,password:string,role:string,label:string} $candidate
     * @return list<array{username:string,password:string,role:string,label:string}>
     */
    private function appendUniqueUser(array $users, array $candidate): array
    {
        if ($candidate['username'] === '' || !$this->isAcceptablePassword($candidate['password'])) {
            return $users;
        }

        foreach ($users as $existing) {
            if (
                hash_equals($existing['username'], $candidate['username'])
                && hash_equals($existing['password'], $candidate['password'])
            ) {
                return $users;
            }
        }

        $users[] = $candidate;

        return $users;
    }

    /** Reject missing, short, and well-known placeholder passwords. */
    private function isAcceptablePassword(string $password): bool
    {
        if (strlen($password) < 12) {
            return false;
        }

        return !in_array(strtolower(trim($password)), [
            'change-me',
            'changeme',
            'password',
            'administrator',
        ], true);
    }

    /**
     * @param array{username:string,role:string,label:string} $identity
     */
    private function persistIdentity(Session $session, array $identity): void
    {
        $session->write('admin.auth_user', $identity['username']);
        $session->write('admin.role', $identity['role']);
        $session->write('admin.auth_label', $identity['label']);
        $session->write('admin.role_locked', true);
    }

    /** Return a generic Basic authentication challenge. */
    private function deny(): ResponseInterface
    {
        $res = new Response();

        return $res->withStatus(401)
            ->withHeader('WWW-Authenticate', 'Basic realm="Admin", charset="UTF-8"')
            ->withStringBody('Authentication required');
    }
}
