<?php

namespace BlueFission\BlueCore\Contracts;

interface IAddOnLifecycleManager
{
    public function install($name, bool $installDependencies = false): array;
    public function uninstall($addOnId, bool $removeDependencies = false): array;
    public function activate($addOnId): array;
    public function deactivate($addOnId): array;
    public function migrate($addOnId): array;
}
