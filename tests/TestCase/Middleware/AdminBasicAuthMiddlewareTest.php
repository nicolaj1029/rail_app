<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\AdminBasicAuthMiddleware;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AdminBasicAuthMiddlewareTest extends TestCase
{
    /**
     * @var array<string,string|false>
     */
    private array $previousEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['ADMIN_JURIST_PASS', 'ADMIN_OPERATOR_PASS'] as $name) {
            $this->previousEnvironment[$name] = getenv($name);
            putenv($name);
        }
        Configure::delete('AdminUsers');
    }

    protected function tearDown(): void
    {
        foreach ($this->previousEnvironment as $name => $value) {
            $value === false ? putenv($name) : putenv($name . '=' . $value);
        }
        Configure::delete('AdminAuth');
        Configure::delete('AdminUsers');
        parent::tearDown();
    }

    public function testInsecureLegacyDefaultCredentialIsRejected(): void
    {
        Configure::write('AdminAuth', [
            'enabled' => true,
            'username' => 'admin',
            'password' => 'changeme',
        ]);
        $request = $this->requestWithServerAuth('admin', 'changeme');

        $response = (new AdminBasicAuthMiddleware())->process($request, $this->okHandler());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testNoConfiguredCredentialFailsClosed(): void
    {
        Configure::write('AdminAuth', ['enabled' => true]);
        $request = new ServerRequest([
            'url' => '/admin/desk',
            'environment' => ['HTTP_HOST' => 'admin.example.com', 'REQUEST_URI' => '/admin/desk'],
        ]);

        $response = (new AdminBasicAuthMiddleware())->process($request, $this->okHandler());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testShortConfiguredCredentialFailsClosed(): void
    {
        Configure::write('AdminAuth', ['enabled' => true]);
        Configure::write('AdminUsers', [[
            'username' => 'jurist',
            'password' => 'too-short',
            'role' => 'jurist',
        ]]);
        $request = $this->requestWithServerAuth('jurist', 'too-short');

        $response = (new AdminBasicAuthMiddleware())->process($request, $this->okHandler());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testConfiguredUserWorksThroughDreamHostForwardedAuthorizationHeader(): void
    {
        Configure::write('AdminAuth', ['enabled' => true]);
        Configure::write('AdminUsers', [[
            'username' => 'jurist',
            'password' => 'strong-test-password',
            'role' => 'jurist',
            'label' => 'Jurist',
        ]]);
        $encoded = base64_encode('jurist:strong-test-password');
        $request = new ServerRequest([
            'url' => '/admin/desk',
            'environment' => [
                'HTTP_HOST' => 'admin.example.com',
                'REQUEST_URI' => '/admin/desk',
                'REDIRECT_HTTP_AUTHORIZATION' => 'Basic ' . $encoded,
            ],
        ]);

        $response = (new AdminBasicAuthMiddleware())->process($request, $this->okHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    private function requestWithServerAuth(string $username, string $password): ServerRequest
    {
        return new ServerRequest([
            'url' => '/admin/desk',
            'environment' => [
                'HTTP_HOST' => 'admin.example.com',
                'REQUEST_URI' => '/admin/desk',
                'PHP_AUTH_USER' => $username,
                'PHP_AUTH_PW' => $password,
            ],
        ]);
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
