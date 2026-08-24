<?php

namespace BlueFission\BlueCore\Contracts;

use BlueFission\BlueCore\Registration\RegistrationPlan;
use BlueFission\Services\Application;

interface IAddOnLifecycleManager
{
    public function install($name, bool $installDependencies = false): array;
    public function installAll(bool $installDependencies = false): array;
    public function uninstall($addOnId, bool $removeDependencies = false): array;
    public function activate($addOnId): array;
    public function activateAll(): array;
    public function deactivate($addOnId): array;
    public function migrate($addOnId): array;
    public function deactivateAll(): array;
    public function loadActivatedAddOns(
        ?Application $application = null,
        ?RegistrationPlan $plan = null
    ): array;
}
