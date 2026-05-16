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

        $credentials = $this->extractCredentials($request);
        if ($credentials === null) {
            return $this->deny();
        }

        [$username, $password] = $credentials;
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

    /**
     * @return array{0:string,1:string}|null
     */
    private function extractCredentials(ServerRequestInterface $request): ?array
    {
        $server = $request->getServerParams();

        $phpAuthUser = (string)($server['PHP_AUTH_USER'] ?? '');
        if ($phpAuthUser !== '') {
            return [$phpAuthUser, (string)($server['PHP_AUTH_PW'] ?? '')];
        }

        $headerCandidates = [
            $request->getHeaderLine('Authorization'),
            (string)($server['HTTP_AUTHORIZATION'] ?? ''),
            (string)($server['REDIRECT_HTTP_AUTHORIZATION'] ?? ''),
        ];

        foreach ($headerCandidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            if (!preg_match('/^Basic\s+(.*)$/i', $candidate, $m)) {
                continue;
            }

            $decoded = base64_decode($m[1] ?? '', true) ?: '';
            if (!str_contains($decoded, ':')) {
                continue;
            }

            return explode(':', $decoded, 2);
        }

        return null;
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
