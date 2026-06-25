<?php

namespace BlueFission\Tests\BlueCore\Contracts;

use BlueFission\BlueCore\Contracts\IAddOnLifecycleManager;
use BlueFission\BlueCore\Contracts\IApplicationRegistrar;
use BlueFission\BlueCore\Contracts\IThemeRegistry;
use BlueFission\BlueCore\Registration\RegistrationPlan;
use PHPUnit\Framework\TestCase;

class RegistrationPlanTest extends TestCase
{
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
}
