<?php

namespace BlueFission\Tests\BlueCore;

use BlueFission\BlueCore\Engine;
use BlueFission\BlueCore\Gateway\GatewayDenied;
use BlueFission\BlueCore\Hooks\LifecycleFailure;
use BlueFission\DevElation as Dev;
use BlueFission\Services\Gateway;
use BlueFission\Services\Request;
use BlueFission\Str;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EngineHookTest extends TestCase
{
    private array $server;

    private array $argv;

    private string|false $appKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $_SERVER;
        $this->argv = $GLOBALS['argv'] ?? [];
        $this->appKey = getenv('APP_KEY');
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $GLOBALS['argv'] = [__FILE__];
        putenv('APP_KEY=engine-hook-test-key');
        $this->resetDevElation();
    }

    protected function tearDown(): void
    {
        $this->resetDevElation();
        $_SERVER = $this->server;
        $GLOBALS['argv'] = $this->argv;
        putenv($this->appKey === false ? 'APP_KEY' : 'APP_KEY=' . $this->appKey);

        parent::tearDown();
    }

    public function testEngineActionsFollowLifecycleAndPriorityOrder(): void
    {
        $events = [];
        Dev::up();
        Dev::action(Engine::HOOK_PROCESS_BEFORE, function () use (&$events): void {
            $events[] = 'process-late';
        }, 20);
        Dev::action(Engine::HOOK_PROCESS_BEFORE, function () use (&$events): void {
            $events[] = 'process-early';
        }, 5);
        Dev::action(Engine::HOOK_PROCESS_AFTER, function () use (&$events): void {
            $events[] = 'process-after';
        });
        Dev::action(Engine::HOOK_RUN_BEFORE, function () use (&$events): void {
            $events[] = 'run-before';
        });
        Dev::action(Engine::HOOK_RUN_AFTER, function (Engine $result, Engine $engine) use (&$events): void {
            $this->assertSame($engine, $result);
            $events[] = 'run-after';
        });

        $engine = $this->engine('/hooks');
        $engine->map('get', 'hooks', fn(): string => 'hooked');

        ob_start();
        $result = $engine->args()->process()->run();
        $response = ob_get_clean();

        $this->assertSame($engine, $result);
        $this->assertSame('hooked', $response);
        $this->assertSame([
            'process-early',
            'process-late',
            'process-after',
            'run-before',
            'run-after',
        ], $events);
    }

    public function testEngineActionsRemainInactiveUntilDevElationIsEnabled(): void
    {
        $events = [];
        Dev::action(Engine::HOOK_PROCESS_BEFORE, function () use (&$events): void {
            $events[] = 'process-before';
        });

        $engine = $this->engine('/inactive');
        $engine->map('get', 'inactive', fn(): string => 'inactive');

        ob_start();
        $engine->args()->process()->run();
        ob_end_clean();

        $this->assertSame([], $events);
    }

    public function testGatewayDenialDispatchesHandledOutcomeActions(): void
    {
        $events = [];
        Dev::up();
        $this->recordActions($events, [
            Engine::HOOK_PROCESS_BEFORE => 'process-before',
            Engine::HOOK_PROCESS_DENIED => 'process-denied',
            Engine::HOOK_PROCESS_AFTER => 'process-after',
            Engine::HOOK_RUN_DENIED => 'run-denied',
        ]);

        $engine = $this->engine('/denied');
        $engine->gateway('deny-hooks', HookDenyingGateway::class);
        $engine->map('get', 'denied', fn(): string => 'unreachable')->gateway('deny-hooks');

        $engine->args()->process()->run();

        $this->assertTrue($engine->denied());
        $this->assertSame([
            'process-before',
            'process-denied',
            'process-after',
            'run-denied',
        ], $events);
    }

    public function testProcessFailureDispatchesFailedWithoutAfter(): void
    {
        $events = [];
        $failure = null;
        Dev::up();
        $this->recordActions($events, [Engine::HOOK_PROCESS_BEFORE => 'process-before']);
        Dev::action(Engine::HOOK_PROCESS_FAILED, function (LifecycleFailure $context) use (&$events, &$failure): void {
            $events[] = 'process-failed';
            $failure = $context;
        });
        $this->recordActions($events, [Engine::HOOK_PROCESS_AFTER => 'process-after']);

        $engine = $this->engine('/process-failure');
        $engine->gateway('fail-hooks', HookFailingGateway::class);
        $engine->map('get', 'process-failure', fn(): string => 'unreachable')->gateway('fail-hooks');

        try {
            $engine->args()->process();
            $this->fail('The failing gateway did not throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('gateway failure', $exception->getMessage());
        }

        $this->assertSame(['process-before', 'process-failed'], $events);
        $this->assertInstanceOf(LifecycleFailure::class, $failure);
        $this->assertSame(Engine::HOOK_PROCESS_FAILED, $failure->hook());
        $this->assertSame('process', $failure->operation());
        $this->assertSame($engine->name(), $failure->dispatcher());
        $this->assertSame(RuntimeException::class, $failure->exceptionType());
        $this->assertSame(0, $failure->exceptionCode());
    }

    public function testRunFailureDispatchesFailedWithoutAfter(): void
    {
        $events = [];
        Dev::up();
        $this->recordActions($events, [
            Engine::HOOK_RUN_BEFORE => 'run-before',
            Engine::HOOK_RUN_FAILED => 'run-failed',
            Engine::HOOK_RUN_AFTER => 'run-after',
        ]);

        $engine = $this->engine('/run-failure');
        $engine->map('get', 'run-failure', function (): string {
            throw new RuntimeException('controller failure');
        });
        $engine->args()->process();

        try {
            $engine->run();
            $this->fail('The failing controller did not throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('controller failure', $exception->getMessage());
        }

        $this->assertSame(['run-before', 'run-failed'], $events);
    }

    public function testBootstrapFailureDispatchesFailedWithoutAfter(): void
    {
        $events = [];
        Dev::up();
        $this->recordActions($events, [
            Engine::HOOK_BOOTSTRAP_BEFORE => 'bootstrap-before',
            Engine::HOOK_BOOTSTRAP_FAILED => 'bootstrap-failed',
            Engine::HOOK_BOOTSTRAP_AFTER => 'bootstrap-after',
        ]);

        $engine = new HookFailingBootstrapEngine([
            'name' => 'bootstrap-hook-test-' . Str::rand('', 12),
        ]);

        try {
            $engine->bootstrap();
            $this->fail('The failing bootstrap did not throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('configuration failure', $exception->getMessage());
        }

        $this->assertSame(['bootstrap-before', 'bootstrap-failed'], $events);
    }

    public function testEngineHookReentryIsSuppressedByDefault(): void
    {
        $invocations = 0;
        Dev::up();
        Dev::action(Engine::HOOK_PROCESS_BEFORE, function (Engine $engine) use (&$invocations): void {
            $invocations++;
            $engine->process();
        });

        $engine = $this->engine('/reentry-default');
        $engine->map('get', 'reentry-default', fn(): string => 'ready');
        $engine->args()->process();

        $this->assertSame(1, $invocations);
    }

    public function testEngineHookReentryRequiresAnExplicitBoundedDepth(): void
    {
        $invocations = 0;
        Dev::up();
        Dev::action(Engine::HOOK_PROCESS_BEFORE, function (Engine $engine) use (&$invocations): void {
            $invocations++;

            if ($invocations < 3) {
                $engine->process();
            }
        });

        $engine = $this->engine('/reentry-opt-in');
        $engine->map('get', 'reentry-opt-in', fn(): string => 'ready');
        $engine->args()->hookRecursionDepth(Engine::HOOK_PROCESS_BEFORE, 2)->process();

        $this->assertSame(2, $invocations);
    }

    public function testFailureHookDoesNotRedispatchItself(): void
    {
        $invocations = 0;
        Dev::up();

        $engine = $this->engine('/failure-reentry');
        $engine->gateway('fail-reentry-hooks', HookFailingGateway::class);
        $engine->map('get', 'failure-reentry', fn(): string => 'unreachable')->gateway('fail-reentry-hooks');
        $engine->args();

        Dev::action(Engine::HOOK_PROCESS_FAILED, function () use (&$invocations, $engine): void {
            $invocations++;

            try {
                $engine->process();
            } catch (RuntimeException) {
            }
        });

        try {
            $engine->process();
            $this->fail('The failing gateway did not throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('gateway failure', $exception->getMessage());
        }

        $this->assertSame(1, $invocations);
    }

    public function testFailureHooksCannotOptIntoRecursiveDispatch(): void
    {
        $engine = $this->engine('/failure-recursion-config');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('failure hooks');

        $engine->hookRecursionDepth(Engine::HOOK_PROCESS_FAILED, 2);
    }

    private function engine(string $uri): Engine
    {
        $_SERVER['REQUEST_URI'] = $uri;

        return new Engine(['name' => 'engine-hook-test-' . Str::rand('', 12)]);
    }

    private function recordActions(array &$events, array $actions): void
    {
        foreach ($actions as $hook => $label) {
            Dev::action($hook, function () use (&$events, $label): void {
                $events[] = $label;
            });
        }
    }

    private function resetDevElation(): void
    {
        Dev::down();
        $reflection = new \ReflectionClass(Dev::class);

        foreach (['_filters', '_actions', '_listeners', '_config'] as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $property->setValue(null, []);
        }
    }
}

class HookDenyingGateway extends Gateway
{
    public function process(Request $request, &$arguments): void
    {
        throw new GatewayDenied('Denied by test gateway.', 403);
    }
}

class HookFailingGateway extends Gateway
{
    public function process(Request $request, &$arguments): void
    {
        throw new RuntimeException('gateway failure');
    }
}

class HookFailingBootstrapEngine extends Engine
{
    public function loadConfiguration(): void
    {
        throw new RuntimeException('configuration failure');
    }
}

