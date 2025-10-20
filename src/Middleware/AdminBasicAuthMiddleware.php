<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Core\Configure;
use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AdminBasicAuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (str_starts_with($path, '/admin')) {
            $cfg = (array)Configure::read('AdminAuth');
            $user = $cfg['username'] ?? 'admin';
            $pass = $cfg['password'] ?? 'changeme';

            $hdr = $request->getHeaderLine('Authorization');
            if (!preg_match('/^Basic\s+(.*)$/i', $hdr, $m)) {
                return $this->deny();
            }
            $decoded = base64_decode($m[1] ?? '', true) ?: '';
            if (!str_contains($decoded, ':')) {
                return $this->deny();
            }
            [$u, $p] = explode(':', $decoded, 2);
            if (!hash_equals($user, $u) || !hash_equals($pass, $p)) {
                return $this->deny();
            }
        }
        return $handler->handle($request);
    }

    private function deny(): ResponseInterface
    {
        $res = new Response();
        return $res->withStatus(401)
            ->withHeader('WWW-Authenticate', 'Basic realm="Admin", charset="UTF-8"')
            ->withStringBody('Authentication required');
    }
}
