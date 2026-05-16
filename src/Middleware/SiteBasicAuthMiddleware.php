<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Core\Configure;
use Cake\Http\Response;
use function Cake\Core\env;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SiteBasicAuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->isEnabled()) {
            return $handler->handle($request);
        }

        $hdr = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Basic\s+(.*)$/i', $hdr, $m)) {
            return $this->deny();
        }

        $decoded = base64_decode($m[1] ?? '', true) ?: '';
        if (!str_contains($decoded, ':')) {
            return $this->deny();
        }

        [$username, $password] = explode(':', $decoded, 2);
        if (!$this->credentialsMatch($username, $password)) {
            return $this->deny();
        }

        return $handler->handle($request);
    }

    private function isEnabled(): bool
    {
        $config = (array)Configure::read('SiteAccess');

        return (bool)($config['enabled'] ?? false);
    }

    private function credentialsMatch(string $username, string $password): bool
    {
        $config = (array)Configure::read('SiteAccess');
        $expectedUser = trim((string)($config['username'] ?? env('SITE_BASIC_AUTH_USER', '')));
        $expectedPassword = (string)($config['password'] ?? env('SITE_BASIC_AUTH_PASS', ''));

        if ($expectedUser === '' || $expectedPassword === '') {
            return false;
        }

        return hash_equals($expectedUser, $username) && hash_equals($expectedPassword, $password);
    }

    private function deny(): ResponseInterface
    {
        $config = (array)Configure::read('SiteAccess');
        $realm = trim((string)($config['realm'] ?? 'Preview'));
        if ($realm === '') {
            $realm = 'Preview';
        }

        $res = new Response();

        return $res->withStatus(401)
            ->withHeader('WWW-Authenticate', sprintf('Basic realm="%s", charset="UTF-8"', addslashes($realm)))
            ->withStringBody('Authentication required');
    }
}
