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
        $config = (array)Configure::read('PublicSite');
        if (empty($config['enabled'])) {
            return $handler->handle($request);
        }

        $landingPath = $this->normalizePath((string)($config['landingPath'] ?? '/passenger/start'));
        $path = $this->normalizePath($request->getUri()->getPath());

        if ($path === '/' || $path === '/pages/home') {
            return (new Response())
                ->withStatus(302)
                ->withHeader('Location', $landingPath);
        }

        return $handler->handle($request);
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
}
