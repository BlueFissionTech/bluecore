<?php

namespace BlueFission\Tests\BlueCore\Gateway;

use BlueFission\BlueCore\Auth;
use BlueFission\BlueCore\Engine;
use BlueFission\BlueCore\Gateway\AuthenticationGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AuthenticationFailureTest extends TestCase
{
    private array $server;
    private array $argv;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->argv = $GLOBALS['argv'] ?? [];
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/guarded';
        $GLOBALS['argv'] = [__FILE__];
        AuthenticationFailureController::$constructed = 0;
        AuthenticationFailureController::$executed = 0;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $GLOBALS['argv'] = $this->argv;
        AuthenticationFailureGateway::$auth = null;
        http_response_code(200);
    }

    public static function denials(): array
    {
        return [
            'anonymous' => ['anonymous', false],
            'session false' => ['session-false', false],
            'identity exception' => ['identity-error', false],
            'session exception' => ['session-error', false],
            'cleanup exception' => ['anonymous', true],
            'original failure survives cleanup' => ['session-error', true],
        ];
    }

    #[DataProvider('denials')]
    public function testFailureDeniesBeforeControllerPreparation(string $mode, bool $cleanupError): void
    {
        $auth = $this->auth();
        $failure = new \RuntimeException('private provider diagnostic');
        $identity = $auth->expects($this->once())->method('isAuthenticated');
        if ($mode === 'identity-error') {
            $identity->willThrowException($failure);
        } else {
            $identity->willReturn($mode !== 'anonymous');
        }

        if ($mode === 'session-false' || $mode === 'session-error') {
            $session = $auth->expects($this->once())->method('setSession');
            $mode === 'session-error'
                ? $session->willThrowException($failure)
                : $session->willReturn(false);
        } else {
            $auth->expects($this->never())->method('setSession');
        }
        $cleanup = $auth->expects($this->once())->method('destroySession');
        $cleanupError
            ? $cleanup->willThrowException(new \RuntimeException('private cleanup diagnostic'))
            : $cleanup->willReturn(true);

        $engine = $this->engine($auth);
        ob_start();
        try {
            $engine->args()->process()->run();
        } finally {
            $response = ob_get_clean();
        }

        $this->assertTrue($engine->denied());
        $this->assertSame(302, $engine->denial()?->statusCode());
        $this->assertSame('Authentication required.', $engine->denial()?->getMessage());
        $this->assertSame('', $response);
        $this->assertSame(0, AuthenticationFailureController::$constructed);
        $this->assertSame(0, AuthenticationFailureController::$executed);
        if ($mode === 'identity-error' || $mode === 'session-error') {
            $this->assertSame($failure, $engine->denial()?->getPrevious());
        }
    }

    public function testConfirmedSessionStillDispatches(): void
    {
        $auth = $this->auth();
        $auth->expects($this->once())->method('isAuthenticated')->willReturn(true);
        $auth->expects($this->once())->method('setSession')->willReturn(true);
        $auth->expects($this->never())->method('destroySession');
        $engine = $this->engine($auth);
        ob_start();
        try {
            $engine->args()->process()->run();
        } finally {
            $response = ob_get_clean();
        }
        $this->assertFalse($engine->denied());
        $this->assertSame('allowed', $response);
        $this->assertSame(1, AuthenticationFailureController::$constructed);
        $this->assertSame(1, AuthenticationFailureController::$executed);
    }

    private function auth(): Auth
    {
        return $this->getMockBuilder(Auth::class)->disableOriginalConstructor()
            ->onlyMethods(['isAuthenticated', 'setSession', 'destroySession'])->getMock();
    }

    private function engine(Auth $auth): Engine
    {
        AuthenticationFailureGateway::$auth = $auth;
        $engine = new Engine(['name' => 'authentication-failure-' . bin2hex(random_bytes(6))]);
        $engine->gateway('auth-fixture', AuthenticationFailureGateway::class);
        $engine->map('get', 'guarded', [AuthenticationFailureController::class, 'handle'])
            ->gateway('auth-fixture');
        return $engine;
    }
}

class AuthenticationFailureGateway extends AuthenticationGateway
{
    public static ?Auth $auth = null;

    public function __construct()
    {
        parent::__construct(self::$auth);
    }

    public function redirect(): void
    {
        http_response_code(302);
    }
}

class AuthenticationFailureController
{
    public static int $constructed = 0;
    public static int $executed = 0;

    public function __construct()
    {
        self::$constructed++;
    }

    public function handle(): string
    {
        self::$executed++;
        return 'allowed';
    }
}
