<?php

namespace BlueFission\BlueCore\Installers;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;

class AddOnInstaller extends LibraryInstaller implements PluginInterface
{
    public function __construct(IOInterface $io, Composer $composer)
    {
        parent::__construct($io, $composer, 'opus-addon');
    }

    public function getInstallPath(PackageInterface $package)
    {
        $name = $package->getPrettyName();
        $name = preg_replace('/^bluefission\//', '', $name);
        return 'addons/' . $name;
    }

    public function activate(Composer $composer, IOInterface $io)
    {
        $composer->getInstallationManager()->addInstaller($this);
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        // no-op
    }

    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // no-op
    }
}
