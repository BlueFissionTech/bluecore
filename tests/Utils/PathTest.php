<?php

namespace BlueFission\Tests\Utils;

use BlueFission\Utils\Path;
use BlueFission\Utils\File;
use PHPUnit\Framework\TestCase;

class PathTest extends TestCase
{
    private $tmpDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-tests';
        $this->tmpDir = $base . DIRECTORY_SEPARATOR . uniqid('path-', true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testEnsureDirCreatesAndNormalizes(): void
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . 'nested' . '/dir';
        $normalized = Path::ensureDir($path);

        $this->assertTrue(is_dir($normalized));
        $this->assertSame(Path::normalize($path), $normalized);
    }

    public function testEnsureDirThrowsForFilePath(): void
    {
        $filePath = File::ensureFile($this->tmpDir . DIRECTORY_SEPARATOR . 'file.txt', 'data', true);

        $this->expectException(\RuntimeException::class);
        Path::ensureDir($filePath);
    }

    private function removeDir($dir): void
    {
        if (!$dir || !is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
