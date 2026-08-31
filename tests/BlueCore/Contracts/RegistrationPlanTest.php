<?php

namespace BlueFission\Tests\BlueCore\Contracts;

use BlueFission\BlueCore\Contracts\IAddOnLifecycleManager;
use BlueFission\BlueCore\Contracts\IApplicationRegistrar;
use BlueFission\BlueCore\Contracts\IThemeRegistry;
use BlueFission\BlueCore\Hooks\LifecycleFailure;
use BlueFission\BlueCore\Registration\RegistrationEntry;
use BlueFission\BlueCore\Registration\RegistrationPlan;
use BlueFission\DevElation as Dev;
use PHPUnit\Framework\TestCase;

class RegistrationPlanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDevElation();
    }

    protected function tearDown(): void
    {
        $this->resetDevElation();
        parent::tearDown();
    }

    public function testRegistrationPlanCollectsFrameworkContributions(): void
    {
        $plan = (new RegistrationPlan())
            ->service('cache', 'CacheService')
            ->delegate('boot', 'BootDelegate')
            ->binding('repository.users', 'UserRepository')
            ->theme('admin', 'resource/markup/admin')
            ->addon('reports', ['version' => '1.0.0']);

        $this->assertSame('CacheService', $plan->entries('services')['cache']);
        $this->assertSame('BootDelegate', $plan->entries('delegates')['boot']);
        $this->assertSame('UserRepository', $plan->entries('bindings')['repository.users']);
        $this->assertSame('resource/markup/admin', $plan->entries('themes')['admin']['location']);
        $this->assertSame('1.0.0', $plan->entries('addons')['reports']['version']);
    }

    public function testRegistrationPlanRejectsUnknownSectionsAndEmptyNames(): void
    {
        $plan = new RegistrationPlan();

        $this->expectException(\InvalidArgumentException::class);
        $plan->entries('missing');
    }

    public function testRegistrationContractsAreDefined(): void
    {
        $this->assertTrue(interface_exists(IApplicationRegistrar::class));
        $this->assertTrue(interface_exists(IAddOnLifecycleManager::class));
        $this->assertTrue(interface_exists(IThemeRegistry::class));
    }

    public function testRegistrationEntriesCanBeFilteredAndObservedInPriorityOrder(): void
    {
        $events = [];
        Dev::up();
        Dev::filter(
            RegistrationPlan::FILTER_ENTRY,
            function (RegistrationEntry $entry) use (&$events): RegistrationEntry {
                $events[] = 'filter';

                return new RegistrationEntry(
                    $entry->section(),
                    $entry->name(),
                    $entry->definition() . '-filtered'
                );
            },
            5
        );
        Dev::action(
            RegistrationPlan::HOOK_ENTRY_ADDED,
            function (RegistrationEntry $entry) use (&$events): void {
                $events[] = 'added:' . $entry->name();
            },
            10
        );

        $plan = (new RegistrationPlan())->service('cache', 'service');

        $this->assertSame('service-filtered', $plan->entries('services')['cache']);
        $this->assertSame(['filter', 'added:cache'], $events);
    }

    public function testInvalidRegistrationFilterResultDispatchesSanitizedFailure(): void
    {
        $failure = null;
        Dev::up();
        Dev::filter(RegistrationPlan::FILTER_ENTRY, fn(): array => []);
        Dev::action(
            RegistrationPlan::HOOK_ENTRY_FAILED,
            function (LifecycleFailure $context) use (&$failure): void {
                $failure = $context;
            }
        );

        try {
            (new RegistrationPlan())->service('cache', 'service');
            $this->fail('Invalid filter result was accepted.');
        } catch (\UnexpectedValueException $exception) {
            $this->assertStringContainsString('RegistrationEntry', $exception->getMessage());
        }

        $this->assertInstanceOf(LifecycleFailure::class, $failure);
        $this->assertSame(RegistrationPlan::HOOK_ENTRY_FAILED, $failure->hook());
        $this->assertSame('compose', $failure->operation());
        $this->assertSame(RegistrationPlan::class, $failure->dispatcher());
        $this->assertSame(\UnexpectedValueException::class, $failure->exceptionType());
    }

    public function testRegistrationEntryFilterReentryIsSuppressed(): void
    {
        $invocations = 0;
        $plan = new RegistrationPlan();
        Dev::up();
        Dev::filter(
            RegistrationPlan::FILTER_ENTRY,
            function (RegistrationEntry $entry) use (&$invocations, $plan): RegistrationEntry {
                $invocations++;

                if ($invocations === 1) {
                    $plan->service('nested', 'ready');
                }

                return $entry;
            }
        );

        $plan->service('root', 'ready');

        $this->assertSame(1, $invocations);
        $this->assertSame(['nested', 'root'], array_keys($plan->entries('services')));
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
