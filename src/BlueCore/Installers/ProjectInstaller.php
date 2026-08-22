<?php

namespace BlueFission\BlueCore\Installers;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use RuntimeException;
use Throwable;

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
        return parent::install($repo, $package)->then(function () use ($repo, $package): void {
            try {
                $this->copyProjectOverrides($package);
            } catch (Throwable $exception) {
                if ($repo->hasPackage($package)) {
                    $repo->removePackage($package);
                }

                $this->filesystem->removeDirectory($this->getInstallPath($package));

                throw new RuntimeException(
                    'Unable to install project overrides for ' . $package->getPrettyName() . ': ' . $exception->getMessage(),
                    0,
                    $exception
                );
            }
        });
    }

    public function update(
        InstalledRepositoryInterface $repo,
        PackageInterface $initial,
        PackageInterface $target
    ) {
        return parent::update($repo, $initial, $target)->then(function () use ($target): void {
            try {
                $this->copyProjectOverrides($target);
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    'Unable to update project overrides for ' . $target->getPrettyName() . ': ' . $exception->getMessage(),
                    0,
                    $exception
                );
            }
        });
    }

    private function copyProjectOverrides(PackageInterface $package): void
    {
        if ($this->getInstallPath($package) !== 'core') {
            return;
        }

        $projectPath = $this->getInstallPath($package);
        $rootPath = dirname($projectPath);
        $createdFiles = [];
        $createdDirectories = [];

        try {
            $overrideDirectories = ['addons', 'common', 'public', 'resource', 'storage', 'datasources', 'mapping'];

            foreach ($overrideDirectories as $directory) {
                $source = $projectPath . DIRECTORY_SEPARATOR . $directory;

                if (is_dir($source)) {
                    $this->copyMerge(
                        $source,
                        $rootPath . DIRECTORY_SEPARATOR . $directory,
                        $createdFiles,
                        $createdDirectories
                    );
                }
            }

            $overrideFiles = ['Procfile', '.env.example', '.env', 'webpack.config.js', 'terminal', 'websocket-server.php', 'package.json'];

            foreach ($overrideFiles as $file) {
                $source = $projectPath . DIRECTORY_SEPARATOR . $file;

                if (is_file($source)) {
                    $this->copyIfNotExists(
                        $source,
                        $rootPath . DIRECTORY_SEPARATOR . $file,
                        $createdFiles,
                        $createdDirectories
                    );
                }
            }
        } catch (Throwable $exception) {
            $this->rollbackOverrides($createdFiles, $createdDirectories);

            throw $exception;
        }
    }

    private function copyMerge($source, $destination, array &$createdFiles, array &$createdDirectories): void
    {
        $this->ensureDirectory($destination, $createdDirectories);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if (is_dir($item)) {
                $this->ensureDirectory($targetPath, $createdDirectories);
            } elseif (is_file($item)) {
                $this->copyIfNotExists($item->getPathname(), $targetPath, $createdFiles, $createdDirectories);
            }
        }
    }

    private function copyIfNotExists(
        $source,
        $destination,
        array &$createdFiles,
        array &$createdDirectories
    ): void
    {
        if (file_exists($destination) || is_link($destination)) {
            return;
        }

        $this->ensureDirectory(dirname($destination), $createdDirectories);
        $this->filesystem->safeCopy($source, $destination);
        $createdFiles[] = $destination;
    }

    private function ensureDirectory($directory, array &$createdDirectories): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (file_exists($directory) || is_link($directory)) {
            throw new RuntimeException('Override directory is blocked by an existing path: ' . $directory);
        }

        $missing = [];
        $current = $directory;

        while (!is_dir($current)) {
            if (file_exists($current) || is_link($current)) {
                throw new RuntimeException('Override directory is blocked by an existing path: ' . $current);
            }

            $missing[] = $current;
            $parent = dirname($current);

            if ($parent === $current) {
                break;
            }

            $current = $parent;
        }

        $this->filesystem->ensureDirectoryExists($directory);

        foreach (array_reverse($missing) as $createdDirectory) {
            $createdDirectories[] = $createdDirectory;
        }
    }

    private function rollbackOverrides(array $createdFiles, array $createdDirectories): void
    {
        foreach (array_reverse($createdFiles) as $file) {
            if (file_exists($file) || is_link($file)) {
                $this->filesystem->unlink($file);
            }
        }

        foreach (array_reverse($createdDirectories) as $directory) {
            if (is_dir($directory) && count(scandir($directory)) === 2) {
                rmdir($directory);
            }
        }
    }
}
