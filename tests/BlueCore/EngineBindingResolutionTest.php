<?php

namespace BlueFission\Tests\BlueCore;

use BlueFission\BlueCore\Engine;
use BlueFission\BlueCore\Registration\RegistrationResolutionException;
use BlueFission\Services\Application;
use BlueFission\Exceptions\DependencyResolutionException;
use PHPUnit\Framework\TestCase;

interface EngineBindingContract
{
}

final class EngineBindingImplementation implements EngineBindingContract
{
}

final class AlternateEngineBindingImplementation implements EngineBindingContract
{
}

final class FirstTestEngine extends Engine
{
}

final class SecondTestEngine extends Engine
{
}

final class EngineBindingResolutionTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->resetStaticProperty(Application::class, '_instances');
        $this->resetStaticProperty(Engine::class, '_activeInstances');
    }

    public function testApplicationFirstConstructionDoesNotStealEngineBindingResolution(): void
    {
        new Application(['name' => 'application-first']);
        $engine = new Engine(['name' => 'binding-owner']);
        $engine->bind(EngineBindingContract::class, EngineBindingImplementation::class);

        $resolved = Engine::makeInstance(EngineBindingContract::class);

        $this->assertSame($engine, Engine::instance());
        $this->assertInstanceOf(EngineBindingImplementation::class, $resolved);
    }

    public function testConcreteEngineClassesRetainIndependentBindingOwnership(): void
    {
        $first = new FirstTestEngine(['name' => 'first-engine']);
        $second = new SecondTestEngine(['name' => 'second-engine']);
        $first->bind(EngineBindingContract::class, EngineBindingImplementation::class);
        $second->bind(EngineBindingContract::class, AlternateEngineBindingImplementation::class);

        $this->assertInstanceOf(
            EngineBindingImplementation::class,
            FirstTestEngine::makeInstance(EngineBindingContract::class)
        );
        $this->assertInstanceOf(
            AlternateEngineBindingImplementation::class,
            SecondTestEngine::makeInstance(EngineBindingContract::class)
        );
    }

    public function testRootBindingRemainsVisibleAcrossRegistrationPhases(): void
    {
        $engine = new Engine(['name' => 'phase-progression']);
        $engine->bind(EngineBindingContract::class, EngineBindingImplementation::class);

        $resolved = $engine->resolveForPhase(EngineBindingContract::class, 'arguments');

        $this->assertInstanceOf(EngineBindingImplementation::class, $resolved);
    }

    public function testNamedEngineReuseRetainsBindingsForLaterBootPhases(): void
    {
        $engine = new Engine(['name' => 'reboot-engine']);
        $engine->bind(EngineBindingContract::class, EngineBindingImplementation::class);

        $reused = Engine::getInstance('reboot-engine');
        $resolved = $reused->resolveForPhase(EngineBindingContract::class, 'reboot');

        $this->assertSame($engine, $reused);
        $this->assertInstanceOf(EngineBindingImplementation::class, $resolved);
    }

    public function testNamedInstanceUsesTheDevElationSelectionContract(): void
    {
        $first = new Engine(['name' => 'first-named-engine']);
        $second = new Engine(['name' => 'second-named-engine']);
		$first->bind(EngineBindingContract::class, EngineBindingImplementation::class);
		$second->bind(EngineBindingContract::class, AlternateEngineBindingImplementation::class);

        $this->assertSame($first, Engine::instance('first-named-engine'));
        $this->assertSame($second, Engine::instance('second-named-engine'));
        $this->assertSame($second, Engine::instance());
		$this->assertInstanceOf(
			EngineBindingImplementation::class,
			Engine::makeInstance(EngineBindingContract::class, 'first-named-engine')
		);
		$this->assertInstanceOf(
			AlternateEngineBindingImplementation::class,
			Engine::makeInstance(EngineBindingContract::class, $second)
		);
    }

    public function testResolutionFailureNamesTheContractAndLifecyclePhase(): void
    {
        $engine = new Engine(['name' => 'diagnostic-engine']);

        try {
            $engine->resolveForPhase(EngineBindingContract::class, 'boot');
            $this->fail('Expected an unresolved interface to fail.');
        } catch (RegistrationResolutionException $exception) {
            $this->assertSame(EngineBindingContract::class, $exception->diagnostic()['contract']);
            $this->assertSame('boot', $exception->diagnostic()['phase']);
            $this->assertSame(EngineBindingContract::class, $exception->diagnostic()['implementation']);
            $this->assertSame(
                DependencyResolutionException::class,
                $exception->diagnostic()['exception_class']
            );
            $this->assertStringContainsString(EngineBindingContract::class, $exception->getMessage());
            $this->assertStringContainsString('boot', $exception->getMessage());
        }
    }

    private function resetStaticProperty(string $class, string $property): void
    {
        $reflection = new \ReflectionProperty($class, $property);
        $reflection->setValue(null, []);
    }
}
