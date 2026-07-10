<?php

namespace BlueFission\Tests\BlueCore\Gateway;

use BlueFission\BlueCore\Gateway\NoCsrfGateway;
use BlueFission\Services\Request;
use PHPUnit\Framework\TestCase;

class NoCsrfGatewayTest extends TestCase
{
    private array $serverBackup = [];
    private array $postBackup = [];
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->postBackup = $_POST;
        $this->sessionBackup = $_SESSION ?? [];

        $_POST = [];
        $_SESSION = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $_SERVER = $this->serverBackup;
        $_POST = $this->postBackup;
        $_SESSION = $this->sessionBackup;
    }

    public function testInjectsCsrfTokenIntoPostPayload(): void
    {
        $_POST = ['name' => 'example'];
        $arguments = [];

        (new NoCsrfGateway())->process(new Request(), $arguments);

        $this->assertArrayHasKey('_token', $_SESSION);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $_SESSION['_token']);
        $this->assertSame($_SESSION['_token'], $_POST['_token']);
    }

    public function testInjectsCsrfTokenIntoHeaderWhenPostPayloadIsEmpty(): void
    {
        $arguments = [];

        (new NoCsrfGateway())->process(new Request(), $arguments);

        $this->assertArrayHasKey('_token', $_SESSION);
        $this->assertSame($_SESSION['_token'], $_SERVER['HTTP_X_CSRF_TOKEN']);
    }
}
