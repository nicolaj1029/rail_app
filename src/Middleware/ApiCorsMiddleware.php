<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ApiCorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (!str_starts_with($path, '/api/')) {
            return $handler->handle($request);
        }

        $origin = trim((string)$request->getHeaderLine('Origin'));
        $allowedOrigin = $this->allowedOrigin($origin);

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->withCorsHeaders(new Response(204), $allowedOrigin);
        }

        $response = $handler->handle($request);

        return $this->withCorsHeaders($response, $allowedOrigin);
    }

    private function allowedOrigin(string $origin): string
    {
        if ($origin === '') {
            return '';
        }

        if (preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#i', $origin)) {
            return $origin;
        }

        return '';
    }

    private function withCorsHeaders(ResponseInterface $response, string $allowedOrigin): ResponseInterface
    {
        if ($allowedOrigin !== '') {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Vary', 'Origin');
        }

        return $response
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
    }
}
