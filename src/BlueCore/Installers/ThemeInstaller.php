<?php

namespace BlueFission\BlueCore\Installers;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;

class ThemeInstaller extends LibraryInstaller
{
    public function __construct(IOInterface $io, Composer $composer)
    {
        parent::__construct($io, $composer, 'opus-theme');
    }

    public function getInstallPath(PackageInterface $package)
    {
        $name = $package->getPrettyName();
        $name = preg_replace('/^bluefission\//', '', $name);
        return 'resource/markup/' . $name;
    }

    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::uninstall($repo, $package);
    }
}
