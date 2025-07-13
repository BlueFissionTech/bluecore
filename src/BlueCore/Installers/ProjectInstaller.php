<?php

namespace BlueFission\BlueCore\Installers;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;

class ProjectInstaller extends LibraryInstaller
{
    public function __construct(IOInterface $io, Composer $composer)
    {
        parent::__construct($io, $composer, 'opus-project');
    }

    public function getInstallPath(PackageInterface $package)
    {
        // Allow fallback to vendor if a standalone install is desired
        $installAsStandalone = getenv('OPUS_STANDALONE') === '1';

        if ($installAsStandalone) {
            return 'vendor/' . $package->getPrettyName();
        }

        return 'core';
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);

        // Post-install copy logic for override-able dirs
        $projectPath = $this->getInstallPath($package);
        $rootPath = dirname($projectPath); // likely the root of the current repo

        $overrideDirs = ['addons', 'common', 'public', 'resource', 'storage'];

        foreach ($overrideDirs as $dir) {
            $sourceDir = $projectPath . DIRECTORY_SEPARATOR . $dir;
            $rootOverride = $rootPath . DIRECTORY_SEPARATOR . $dir;

            if (is_dir($sourceDir)) {
                $this->copyMerge($sourceDir, $rootOverride);
            }
        }

        $overrideFiles = ['Procfile', '.env.example', '.env', 'webpack.config.js', 'terminal', 'websocket-server.php', 'package.json'];

        // Copy override files to root
        foreach ($overrideFiles as $file) {
            $sourceFile = $projectPath . DIRECTORY_SEPARATOR . $file;
            $rootFile = $rootPath . DIRECTORY_SEPARATOR . $file;

            if (file_exists($sourceFile)) {
                $this->copyIfNotExists($sourceFile, $rootFile);
            }
        }
    }

    private function copyMerge($source, $dest)
    {
        if (!is_dir($source)) return;

        @mkdir($dest, 0755, true);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if (is_dir($item)) {
                @mkdir($targetPath, 0755, true);
            } elseif (is_file($item)) {
                $this->copyIfNotExists($item, $targetPath);
            }
        }
    }

    private function copyIfNotExists($source, $dest)
    {
        if (!file_exists($dest)) {
            copy($source, $dest);
        }
    }

    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // Optional: Add cleanup if needed
        parent::uninstall($repo, $package);
    }
}
