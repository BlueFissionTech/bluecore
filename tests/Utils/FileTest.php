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
        $this->assertSame('first', File::readContents($created));

        File::ensureFile($path, 'second');
        $this->assertSame('first', File::readContents($created));

        File::ensureFile($path, 'second', true);
        $this->assertSame('second', File::readContents($created));
    }

    public function testFileUtilityExtendsDevElationFile(): void
    {
        $this->assertInstanceOf(\BlueFission\Data\File::class, new File());
    }

    public function testWriteAtomicWritesContents(): void
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . 'atomic.txt';

        File::writeAtomic($path, 'hello');
        $this->assertSame('hello', File::readContents($path));

        File::writeAtomic($path, 'world');
        $this->assertSame('world', File::readContents($path));
    }

    public function testReadinessReportsExistingFileWithHash(): void
    {
        $path = File::ensureFile($this->tmpDir . DIRECTORY_SEPARATOR . 'ready.txt', 'ready', true);

        $readiness = File::readiness($path, true);

        $this->assertSame($path, $readiness['normalizedPath']);
        $this->assertSame('file', $readiness['expectedType']);
        $this->assertTrue($readiness['exists']);
        $this->assertTrue($readiness['readable']);
        $this->assertTrue($readiness['writable']);
        $this->assertNull($readiness['reason']);
        $this->assertSame(hash('sha256', 'ready'), $readiness['hash']);
    }

    public function testReadinessReportsMissingFileAgainstWritableParent(): void
    {
        $dir = $this->tmpDir . DIRECTORY_SEPARATOR . 'target';
        $path = $dir . DIRECTORY_SEPARATOR . 'missing.txt';
        \BlueFission\Utils\Path::ensureDir($dir);

        $readiness = File::readiness($path);

        $this->assertFalse($readiness['exists']);
        $this->assertFalse($readiness['readable']);
        $this->assertTrue($readiness['writable']);
        $this->assertSame('missing', $readiness['reason']);
    }

    public function testReadinessRejectsInvalidAndDirectoryPaths(): void
    {
        $this->assertSame('invalid_path', File::readiness('')['reason']);

        $dir = \BlueFission\Utils\Path::ensureDir($this->tmpDir . DIRECTORY_SEPARATOR . 'folder');
        $readiness = File::readiness($dir);

        $this->assertFalse($readiness['exists']);
        $this->assertSame('not_file', $readiness['reason']);
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
