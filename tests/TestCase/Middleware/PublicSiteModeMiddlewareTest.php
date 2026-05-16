<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\PublicSiteModeMiddleware;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PublicSiteModeMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Configure::delete('PublicSite');
        Configure::delete('HostRouting');
    }

    public function testPublicHostBlocksAdminRoutes(): void
    {
        Configure::write('PublicSite', ['enabled' => false, 'landingPath' => '/passenger/start']);
        Configure::write('HostRouting', [
            'adminHosts' => ['admin.example.com'],
            'defaults' => [
                'landingPath' => '/passenger/start',
                'hideTopNav' => true,
                'hidePassengerNav' => true,
                'blockAdminRoutes' => true,
            ],
            'publicHosts' => [
                'rail.example.com' => ['transportMode' => 'rail'],
            ],
        ]);

        $middleware = new PublicSiteModeMiddleware();
        $request = new ServerRequest([
            'url' => '/admin/desk',
            'environment' => [
                'HTTP_HOST' => 'rail.example.com',
                'REQUEST_URI' => '/admin/desk',
                'HTTPS' => 'on',
            ],
        ]);

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testPublicHostRedirectsBareFlowToLanding(): void
    {
        Configure::write('PublicSite', ['enabled' => false, 'landingPath' => '/passenger/start']);
        Configure::write('HostRouting', [
            'defaults' => [
                'landingPath' => '/passenger/start',
                'blockAdminRoutes' => true,
            ],
            'publicHosts' => [
                'rail.example.com' => ['transportMode' => 'rail'],
            ],
        ]);

        $middleware = new PublicSiteModeMiddleware();
        $request = new ServerRequest([
            'url' => '/flow',
            'environment' => [
                'HTTP_HOST' => 'rail.example.com',
                'REQUEST_URI' => '/flow',
                'HTTPS' => 'on',
            ],
        ]);

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/passenger/start', $response->getHeaderLine('Location'));
    }

    public function testPublicHostPassesThroughConcreteFlowAndSetsTransportModeAttribute(): void
    {
        Configure::write('PublicSite', ['enabled' => false, 'landingPath' => '/passenger/start']);
        Configure::write('HostRouting', [
            'defaults' => [
                'landingPath' => '/passenger/start',
                'blockAdminRoutes' => true,
            ],
            'publicHosts' => [
                'rail.example.com' => ['transportMode' => 'rail'],
            ],
        ]);

        $capture = new \stdClass();
        $capture->request = null;
        $middleware = new PublicSiteModeMiddleware();
        $request = new ServerRequest([
            'url' => '/flow/rail/completed',
            'environment' => [
                'HTTP_HOST' => 'rail.example.com',
                'REQUEST_URI' => '/flow/rail/completed',
                'HTTPS' => 'on',
            ],
        ]);

        $response = $middleware->process($request, new class($capture) implements RequestHandlerInterface {
            private \stdClass $capture;

            public function __construct(\stdClass $capture)
            {
                $this->capture = $capture;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->capture->request = $request;
                return new Response();
            }
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertInstanceOf(ServerRequestInterface::class, $capture->request);
        $context = (array)$capture->request?->getAttribute('siteContext', []);
        $this->assertTrue((bool)($context['enabled'] ?? false));
        $this->assertSame('rail', $context['transportMode'] ?? '');
        $this->assertTrue((bool)($context['blockAdminRoutes'] ?? false));
    }

    public function testAdminHostBypassesPublicRedirects(): void
    {
        Configure::write('PublicSite', ['enabled' => false, 'landingPath' => '/passenger/start']);
        Configure::write('HostRouting', [
            'adminHosts' => ['admin.example.com'],
            'defaults' => [
                'landingPath' => '/passenger/start',
                'blockAdminRoutes' => true,
            ],
            'publicHosts' => [
                'rail.example.com' => ['transportMode' => 'rail'],
            ],
        ]);

        $middleware = new PublicSiteModeMiddleware();
        $request = new ServerRequest([
            'url' => '/admin/desk',
            'environment' => [
                'HTTP_HOST' => 'admin.example.com',
                'REQUEST_URI' => '/admin/desk',
                'HTTPS' => 'on',
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
