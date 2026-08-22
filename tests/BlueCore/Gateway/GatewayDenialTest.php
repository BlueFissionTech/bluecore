<?php

namespace BlueFission\Tests\BlueCore\Gateway;

use BlueFission\BlueCore\Auth;
use BlueFission\BlueCore\Engine;
use BlueFission\BlueCore\Gateway\AuthenticationGateway;
use BlueFission\BlueCore\Gateway\GatewayDenied;
use BlueFission\Flag;
use BlueFission\Net\HTTP;
use BlueFission\Services\Gateway;
use BlueFission\Services\Request;
use BlueFission\Str;
use PHPUnit\Framework\TestCase;

class GatewayDenialTest extends TestCase
{
    private array $server;

    private array $argv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $_SERVER;
        $this->argv = $GLOBALS['argv'] ?? [];
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $GLOBALS['argv'] = [__FILE__];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $GLOBALS['argv'] = $this->argv;
        http_response_code(200);

        parent::tearDown();
    }

    public function testDeniedGatewayHaltsMappedController(): void
    {
        $controllerInvoked = Flag::make(false);
        $engine = $this->engine('/protected');
        $engine->gateway('deny', DenyingGateway::class);
        $engine
            ->map('get', 'protected', function () use ($controllerInvoked): string {
                $controllerInvoked->val(true);

                return 'controller';
            })
            ->gateway('deny');

        ob_start();
        $engine->args()->process()->run();
        $response = ob_get_clean();

        $this->assertFalse($controllerInvoked->val());
        $this->assertTrue($engine->denied());
        $this->assertSame(403, $engine->denial()?->statusCode());
        $this->assertSame('forbidden', $response);
    }

    public function testSuccessfulGatewayDispatchRemainsUnchanged(): void
    {
        $controllerInvoked = Flag::make(false);
        $engine = $this->engine('/available');
        $engine->gateway('pass', PassingGateway::class);
        $engine
            ->map('get', 'available', function () use ($controllerInvoked): string {
                $controllerInvoked->val(true);

                return 'available';
            })
            ->gateway('pass');

        ob_start();
        $engine->args()->process()->run();
        $response = ob_get_clean();

        $this->assertTrue($controllerInvoked->val());
        $this->assertFalse($engine->denied());
        $this->assertSame('available', $response);
    }

    public function testAnonymousAuthenticationEmitsOnlyTheLoginRedirect(): void
    {
        $auth = $this->getMockBuilder(Auth::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isAuthenticated', 'destroySession'])
            ->getMock();
        $auth->expects($this->once())->method('isAuthenticated')->willReturn(false);
        $auth->expects($this->once())->method('destroySession');

        $gateway = new class($auth) extends AuthenticationGateway {
            public string $header = '';

            public function redirect(): void
            {
                $this->header = HTTP::headerLine('Location', $this->_redirectUri);
                http_response_code(302);
            }
        };
        $arguments = [];
        $denial = null;

        ob_start();
        try {
            $gateway->process(new Request(), $arguments);
            $this->fail('Anonymous authentication did not deny dispatch.');
        } catch (GatewayDenied $exception) {
            $denial = $exception;
        } finally {
            $response = ob_get_clean();
        }

        $this->assertInstanceOf(GatewayDenied::class, $denial);
        $this->assertSame(302, $denial->statusCode());
        $this->assertSame('Location: /login', $gateway->header);
        $this->assertSame('', $response);
    }

    private function engine(string $uri): Engine
    {
        $_SERVER['REQUEST_URI'] = $uri;

        return new Engine(['name' => 'gateway-test-' . Str::rand('', 12)]);
    }
}

class DenyingGateway extends Gateway
{
    public function process(Request $request, &$arguments): void
    {
        http_response_code(403);
        echo 'forbidden';

        throw new GatewayDenied('Forbidden', 403);
    }
}

class PassingGateway extends Gateway
{
    public function process(Request $request, &$arguments): void
    {
    }
}
