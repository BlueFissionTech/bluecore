<?php

namespace BlueFission\Tests\BlueCore;

use BlueFission\BlueCore\Engine;
use BlueFission\BlueCore\Gateway\GatewayDenied;
use BlueFission\Services\Application;
use BlueFission\Services\Gateway;
use BlueFission\Services\Mapping;
use BlueFission\Services\Request;
use BlueFission\Str;
use BlueFission\Utils\Loader;
use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Public behavior only: no singleton aliases, map copies or private resets. */
final class EngineRoutingContextTest extends TestCase
{
    private array $server;
    private array $argv;
    private string|false $appKey;
    private string $root;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->argv = $GLOBALS['argv'] ?? [];
        $this->appKey = getenv('APP_KEY');
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $GLOBALS['argv'] = [__FILE__];
        putenv('APP_KEY=synthetic-routing-test-key');
        $this->root = sys_get_temp_dir() . '/bluecore-routing-' . Str::rand('', 16);
        mkdir($this->root . '/mapping', 0777, true);
        Loader::instance()->addPath($this->root);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/mapping/*.php') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->root . '/mapping');
        rmdir($this->root);
        $_SERVER = $this->server;
        $GLOBALS['argv'] = $this->argv;
        putenv($this->appKey === false ? 'APP_KEY' : 'APP_KEY=' . $this->appKey);
    }

    public function testBootstrapRegistersAndDispatchesToItsReceiver(): void
    {
        $engine = $this->engine();
        $path = $this->path();
        $engine->configure = static function () use ($path): void {
            Mapping::add(' /' . $path . '/ ', static fn (): string => 'owned', ' owned-route ', ' GET ');
        };
        $base = Application::instance();
        $before = $base->maps();

        $this->assertSame($engine, $engine->bootstrap());
        $this->assertArrayHasKey($path, $engine->maps()['get'] ?? []);
        $this->assertSame('owned-route', $engine->maps()['get'][$path]->name);
        $this->assertSame($before, $base->maps());
        $this->assertSame('owned', $this->dispatch($engine, $path));
    }

    public function testSameMappingFileRegistersOnceForEachIndependentEngine(): void
    {
        $path = $this->path();
        file_put_contents($this->root . '/mapping/routes.php', '<?php '
            . '\\BlueFission\\Services\\Mapping::add('
            . var_export($path, true) . ', static fn (): string => "from-file", "file-route");');
        $first = $this->engine();
        $second = $this->engine();

        $first->bootstrap();
        $second->bootstrap();

        $this->assertArrayHasKey($path, $first->maps()['get'] ?? []);
        $this->assertArrayHasKey($path, $second->maps()['get'] ?? []);
        $this->assertNotSame($first->maps()['get'][$path], $second->maps()['get'][$path]);
        $firstMapping = $first->maps()['get'][$path];
        $first->bootstrap();
        $this->assertSame($firstMapping, $first->maps()['get'][$path]);
        $this->assertCount(1, $first->maps()['get']);
        $this->assertSame('from-file', $this->dispatch($first, $path));
        $this->assertSame('from-file', $this->dispatch($second, $path));
        $this->assertSame($first->hostSession, $first->resolvedSession);
        $this->assertSame($second->hostSession, $second->resolvedSession);
    }

    public function testNoMatchDoesNotExecuteAnotherEnginesRoute(): void
    {
        $path = $this->path();
        $calls = 0;
        $first = $this->engine();
        $second = $this->engine();
        $first->configure = static function () use ($path, &$calls): void {
            Mapping::add($path, static function () use (&$calls): string {
                $calls++;
                return 'first-only';
            });
        };
        $first->bootstrap();
        $second->bootstrap();

        $this->assertSame('', $this->dispatch($second, $path));
        $this->assertFalse($second->denied());
        $this->assertArrayNotHasKey($path, $second->maps()['get'] ?? []);
        $this->assertSame(0, $calls);
        $this->assertSame('first-only', $this->dispatch($first, $path));
        $this->assertSame(1, $calls);
    }

    public function testNestedBootstrapAndExceptionsRestoreThePriorTarget(): void
    {
        $outer = $this->engine();
        $inner = $this->engine();
        $outerPath = $this->path();
        $innerPath = $this->path();
        $legacyPath = $this->path();
        $inner->configure = static function () use ($innerPath): void {
            Mapping::add($innerPath, static fn (): string => 'inner');
            throw new RuntimeException('synthetic-registration-failure');
        };
        $outer->configure = function () use ($inner, $outerPath): void {
            try {
                $inner->bootstrap();
                $this->fail('The registration exception must propagate.');
            } catch (RuntimeException $exception) {
                $this->assertSame('synthetic-registration-failure', $exception->getMessage());
            }
            Mapping::add($outerPath, static fn (): string => 'outer');
        };
        $outer->bootstrap();
        $legacy = Mapping::add($legacyPath, static fn (): string => 'legacy');

        $this->assertSame($legacy, Application::instance()->maps()['get'][$legacyPath]);
        $this->assertArrayHasKey($outerPath, $outer->maps()['get'] ?? []);
        $this->assertArrayNotHasKey($innerPath, $outer->maps()['get'] ?? []);
        $this->assertArrayHasKey($innerPath, $inner->maps()['get'] ?? []);
        $this->assertArrayNotHasKey($outerPath, $inner->maps()['get'] ?? []);
        $this->assertArrayNotHasKey($legacyPath, $outer->maps()['get'] ?? []);
        $this->assertSame('outer', $this->dispatch($outer, $outerPath));
    }

    public function testFailedMappingFileCanBeRetriedWithoutAnotherInstanceTakingOwnership(): void
    {
        $engine = $this->engine();
        $path = $this->path();
        $file = $this->root . '/mapping/retry.php';
        file_put_contents($file, '<?php throw new \\RuntimeException("synthetic-loader-failure");');
        try {
            $engine->bootstrap();
            $this->fail('The mapping file exception must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('synthetic-loader-failure', $exception->getMessage());
        }
        file_put_contents($file, '<?php \\BlueFission\\Services\\Mapping::add('
            . var_export($path, true) . ', static fn (): string => "retried");');
        $engine->bootstrap();

        $this->assertArrayHasKey($path, $engine->maps()['get'] ?? []);
        $this->assertSame('retried', $this->dispatch($engine, $path));
        $this->assertArrayNotHasKey($path, Application::instance()->maps()['get'] ?? []);
    }

    public function testGatewayDenialPreventsControllerEffectsOnScopedRoutes(): void
    {
        $engine = $this->engine();
        $path = $this->path();
        $calls = 0;
        $engine->gateway('routing-denial', RoutingContextDenyGateway::class);
        $engine->configure = static function () use ($path, &$calls): void {
            Mapping::add($path, static function () use (&$calls): string {
                $calls++;
                return 'must-not-run';
            }, 'denied-route')->gateway('routing-denial');
        };
        $engine->bootstrap();

        $this->assertArrayHasKey($path, $engine->maps()['get'] ?? []);
        $this->assertSame(['routing-denial'], $engine->maps()['get'][$path]->gateways());
        $this->assertSame('', $this->dispatch($engine, $path));
        $this->assertTrue($engine->denied());
        $this->assertSame(403, $engine->denial()->statusCode());
        $this->assertSame(0, $calls);
    }

    public function testExplicitTargetOverridesBootScopeWithoutChangingItsReceiver(): void
    {
        $first = $this->engine();
        $second = $this->engine();
        $firstPath = $this->path();
        $secondPath = $this->path();
        $first->configure = static function () use ($firstPath, $secondPath, $second): void {
            Mapping::add($secondPath, static fn (): string => 'explicit-second', '', 'get', $second);
            Mapping::add($firstPath, static fn (): string => 'scoped-first');
        };
        $first->bootstrap();

        $this->assertArrayNotHasKey($secondPath, $first->maps()['get'] ?? []);
        $this->assertArrayNotHasKey($firstPath, $second->maps()['get'] ?? []);
        $this->assertSame('scoped-first', $this->dispatch($first, $firstPath));
        $this->assertSame('explicit-second', $this->dispatch($second, $secondPath));
    }

    public function testSuccessfulNestedBootstrapRestoresOuterRegistration(): void
    {
        $outer = $this->engine();
        $inner = $this->engine();
        $outerPath = $this->path();
        $innerPath = $this->path();
        $inner->configure = static function () use ($innerPath): void {
            Mapping::add($innerPath, static fn (): string => 'inner-ok');
        };
        $outer->configure = static function () use ($inner, $outerPath): void {
            $inner->bootstrap();
            Mapping::add($outerPath, static fn (): string => 'outer-ok');
        };
        $outer->bootstrap();

        $this->assertArrayNotHasKey($innerPath, $outer->maps()['get'] ?? []);
        $this->assertArrayNotHasKey($outerPath, $inner->maps()['get'] ?? []);
        $this->assertSame('outer-ok', $this->dispatch($outer, $outerPath));
        $this->assertSame('inner-ok', $this->dispatch($inner, $innerPath));
    }

    private function engine(): RoutingContextEngine
    {
        return new RoutingContextEngine(['name' => 'routing-' . Str::rand('', 16)]);
    }

    private function path(): string
    {
        return 'routing-' . Str::rand('', 16);
    }

    private function dispatch(Engine $engine, string $path): string
    {
        $_SERVER['REQUEST_URI'] = '/' . $path;
        ob_start();
        try {
            $engine->args()->process()->run();
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}

final class RoutingContextEngine extends Engine
{
    public ?Closure $configure = null;
    public readonly object $hostSession;
    public ?object $resolvedSession = null;

    public function __construct($config = [])
    {
        $this->hostSession = new \stdClass();
        parent::__construct($config);
    }

    public function loadConfiguration(): void
    {
        if ($this->configure !== null) {
            ($this->configure)();
        }
    }

    public function service($serviceName, $call = null)
    {
        if ($serviceName === 'session') {
            return $this->resolvedSession = $this->hostSession;
        }
        return parent::service($serviceName, $call);
    }
}

final class RoutingContextDenyGateway extends Gateway
{
    public function process(Request $request, &$arguments): void
    {
        throw new GatewayDenied('Synthetic routing denial.', 403);
    }
}
