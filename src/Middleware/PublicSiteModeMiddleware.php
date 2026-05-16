<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Core\Configure;
use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PublicSiteModeMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $context = $this->resolveContext($request);
        $request = $request->withAttribute('siteContext', $context);

        if (empty($context['enabled'])) {
            return $handler->handle($request);
        }

        $path = $this->normalizePath($request->getUri()->getPath());
        $landingPath = $this->normalizePath((string)($context['landingPath'] ?? '/passenger/start'));

        if (!empty($context['blockAdminRoutes']) && $this->isAdminPath($path)) {
            return (new Response())->withStatus(404)->withStringBody('Not Found');
        }

        if ($this->shouldRedirectToLanding($path, $landingPath)) {
            return (new Response())
                ->withStatus(302)
                ->withHeader('Location', $landingPath);
        }

        return $handler->handle($request);
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveContext(ServerRequestInterface $request): array
    {
        $publicSite = (array)Configure::read('PublicSite');
        $hostRouting = (array)Configure::read('HostRouting');
        $host = strtolower(trim((string)$request->getUri()->getHost()));

        $defaults = [
            'enabled' => (bool)($publicSite['enabled'] ?? false),
            'isPublicHost' => false,
            'isAdminHost' => false,
            'transportMode' => '',
            'landingPath' => (string)($publicSite['landingPath'] ?? '/passenger/start'),
            'hideTopNav' => (bool)($publicSite['hideTopNav'] ?? false),
            'hidePassengerNav' => (bool)($publicSite['hidePassengerNav'] ?? false),
            'blockAdminRoutes' => false,
            'host' => $host,
        ];

        if ($host === '') {
            return $defaults;
        }

        $adminHosts = array_values(array_filter((array)($hostRouting['adminHosts'] ?? []), 'is_string'));
        if ($this->hostMatchesAny($host, $adminHosts)) {
            return $defaults + [
                'enabled' => false,
                'isAdminHost' => true,
            ];
        }

        $publicDefaults = (array)($hostRouting['defaults'] ?? []);
        foreach ((array)($hostRouting['publicHosts'] ?? []) as $pattern => $config) {
            if (!is_string($pattern) || !$this->hostMatches($host, $pattern)) {
                continue;
            }
            $hostConfig = is_array($config) ? $config : [];

            return [
                'enabled' => true,
                'isPublicHost' => true,
                'isAdminHost' => false,
                'transportMode' => $this->normalizeTransportMode((string)($hostConfig['transportMode'] ?? $publicDefaults['transportMode'] ?? '')),
                'landingPath' => (string)($hostConfig['landingPath'] ?? $publicDefaults['landingPath'] ?? $defaults['landingPath']),
                'hideTopNav' => (bool)($hostConfig['hideTopNav'] ?? $publicDefaults['hideTopNav'] ?? true),
                'hidePassengerNav' => (bool)($hostConfig['hidePassengerNav'] ?? $publicDefaults['hidePassengerNav'] ?? true),
                'blockAdminRoutes' => (bool)($hostConfig['blockAdminRoutes'] ?? $publicDefaults['blockAdminRoutes'] ?? true),
                'host' => $host,
            ];
        }

        return $defaults;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return rtrim($path, '/') ?: '/';
    }

    private function isAdminPath(string $path): bool
    {
        return $path === '/admin' || str_starts_with($path, '/admin/');
    }

    private function normalizeTransportMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        return in_array($mode, ['air', 'rail', 'ferry', 'bus'], true) ? $mode : '';
    }

    /**
     * @param list<string> $patterns
     */
    private function hostMatchesAny(string $host, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->hostMatches($host, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function hostMatches(string $host, string $pattern): bool
    {
        $host = strtolower(trim($host));
        $pattern = strtolower(trim($pattern));
        if ($host === '' || $pattern === '') {
            return false;
        }

        if (!str_contains($pattern, '*')) {
            return $host === $pattern;
        }

        $quoted = preg_quote($pattern, '/');
        $regex = '/^' . str_replace('\*', '.*', $quoted) . '$/i';

        return (bool)preg_match($regex, $host);
    }

    private function shouldRedirectToLanding(string $path, string $landingPath): bool
    {
        if ($path === $landingPath) {
            return false;
        }

        if ($path === '/' || $path === '/pages/home') {
            return true;
        }

        if ($path === '/flow') {
            return true;
        }

        return false;
    }
}
