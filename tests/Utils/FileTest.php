<?php

namespace BlueFission\Tests\Utils;

use BlueFission\Utils\File;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{
    private $tmpDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-tests';
        $this->tmpDir = $base . DIRECTORY_SEPARATOR . uniqid('file-', true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testEnsureFileCreatesParentDirsAndRespectsOverwrite(): void
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'b' . DIRECTORY_SEPARATOR . 'c.txt';

        $created = File::ensureFile($path, 'first');
        $this->assertFileExists($created);
        $this->assertSame('first', file_get_contents($created));

        File::ensureFile($path, 'second');
        $this->assertSame('first', file_get_contents($created));

        File::ensureFile($path, 'second', true);
        $this->assertSame('second', file_get_contents($created));
    }

    public function testWriteAtomicWritesContents(): void
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . 'atomic.txt';

        File::writeAtomic($path, 'hello');
        $this->assertSame('hello', file_get_contents($path));

        File::writeAtomic($path, 'world');
        $this->assertSame('world', file_get_contents($path));
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
