<?php

namespace BlueFission\BlueCore\Installers;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class PluginInstaller implements PluginInterface
{
    protected $installers = [];

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->installers[] = new AddOnInstaller($io, $composer);
        $this->installers[] = new ThemeInstaller($io, $composer);

        foreach ($this->installers as $installer) {
            $composer->getInstallationManager()->addInstaller($installer);
        }
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        foreach ($this->installers as $installer) {
            $composer->getInstallationManager()->removeInstaller($installer);
        }
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        // no-op
    }
}
