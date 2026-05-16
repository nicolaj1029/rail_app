<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\SiteBasicAuthMiddleware;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SiteBasicAuthMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Configure::delete('SiteAccess');
    }

    public function testScopedAuthIgnoresNonMatchingHost(): void
    {
        Configure::write('SiteAccess', [
            'enabled' => true,
            'username' => 'preview',
            'password' => 'secret',
            'realm' => 'Preview',
            'hosts' => ['test.example.com'],
        ]);

        $middleware = new SiteBasicAuthMiddleware();
        $request = new ServerRequest([
            'url' => '/',
            'environment' => [
                'HTTP_HOST' => 'admin.example.com',
                'REQUEST_URI' => '/',
            ],
        ]);

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testScopedAuthRequiresCredentialsOnMatchingHost(): void
    {
        Configure::write('SiteAccess', [
            'enabled' => true,
            'username' => 'preview',
            'password' => 'secret',
            'realm' => 'Preview',
            'hosts' => ['test.example.com'],
        ]);

        $middleware = new SiteBasicAuthMiddleware();
        $request = new ServerRequest([
            'url' => '/',
            'environment' => [
                'HTTP_HOST' => 'test.example.com',
                'REQUEST_URI' => '/',
            ],
        ]);

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Basic realm="Preview"', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function testScopedAuthAcceptsValidCredentialsOnMatchingHost(): void
    {
        Configure::write('SiteAccess', [
            'enabled' => true,
            'username' => 'preview',
            'password' => 'secret',
            'realm' => 'Preview',
            'hosts' => ['test.example.com'],
        ]);

        $middleware = new SiteBasicAuthMiddleware();
        $request = new ServerRequest([
            'url' => '/',
            'environment' => [
                'HTTP_HOST' => 'test.example.com',
                'REQUEST_URI' => '/',
                'PHP_AUTH_USER' => 'preview',
                'PHP_AUTH_PW' => 'secret',
            ],
        ]);

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    private function okHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };
    }
}
