<?php

namespace BlueFission\Tests\Helpers;

use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use PHPUnit\Framework\TestCase;

class ResolvePathHelperTest extends TestCase
{
    public function testApplicationPathsAndWildcardsResolveBeforeProjectFallback(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-resolve-path-' . uniqid();
        $applicationRoot = $root . DIRECTORY_SEPARATOR . 'application';
        $projectRoot = $root . DIRECTORY_SEPARATOR . 'project';

        File::ensureFile(
            $applicationRoot . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php',
            '<?php return [];',
            true
        );
        File::ensureFile(
            $projectRoot . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'fallback.php',
            '<?php return [];',
            true
        );

        $this->assertSame(
            Path::normalize($applicationRoot . DIRECTORY_SEPARATOR . 'common/config/app.php'),
            resolve_path('common/config/app.php', $applicationRoot, $projectRoot)
        );
        $this->assertSame(
            Path::normalize($applicationRoot . DIRECTORY_SEPARATOR . 'common/config/*.php'),
            resolve_path('common/config/*.php', $applicationRoot, $projectRoot)
        );
        $this->assertSame(
            Path::normalize($applicationRoot . DIRECTORY_SEPARATOR . 'common/config/*.php'),
            resolve_path('common\\config/*.php', $applicationRoot, $projectRoot)
        );
        $this->assertSame(
            Path::normalize($projectRoot . DIRECTORY_SEPARATOR . 'common/config/missing-*.php'),
            resolve_path('common/config/missing-*.php', $applicationRoot, $projectRoot)
        );
        $this->assertSame(
            Path::normalize($projectRoot . DIRECTORY_SEPARATOR . 'common/config/[invalid.php'),
            resolve_path('common/config/[invalid.php', $applicationRoot, $projectRoot)
        );

        $this->removeDir($root);
    }

    private function removeDir(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
