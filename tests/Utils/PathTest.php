<?php

namespace BlueFission\Tests\Utils;

use BlueFission\Func;
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

    public function testPathUtilityExtendsDevElationDirectory(): void
    {
        $this->assertTrue(is_subclass_of(Path::class, \BlueFission\Data\Directory::class));
    }

    public function testEnsureDirThrowsForFilePath(): void
    {
        $filePath = File::ensureFile($this->tmpDir . DIRECTORY_SEPARATOR . 'file.txt', 'data', true);

        $this->expectException(\RuntimeException::class);
        Path::ensureDir($filePath);
    }

    public function testReadinessReportsExistingDirectory(): void
    {
        $dir = Path::ensureDir($this->tmpDir . DIRECTORY_SEPARATOR . 'ready');

        $readiness = Path::readiness($dir);

        $this->assertSame($dir, $readiness['normalizedPath']);
        $this->assertSame('directory', $readiness['expectedType']);
        $this->assertTrue($readiness['exists']);
        $this->assertTrue($readiness['readable']);
        $this->assertTrue($readiness['writable']);
        $this->assertNull($readiness['reason']);
        $this->assertNull($readiness['hash']);
    }

    public function testReadinessReportsMissingAndInvalidDirectories(): void
    {
        $missing = Path::readiness($this->tmpDir . DIRECTORY_SEPARATOR . 'missing');

        $this->assertFalse($missing['exists']);
        $this->assertSame('missing', $missing['reason']);
        $this->assertSame('invalid_path', Path::readiness('')['reason']);
    }

    public function testReadinessReportsFileAtDirectoryPath(): void
    {
        $filePath = File::ensureFile($this->tmpDir . DIRECTORY_SEPARATOR . 'file.txt', 'data', true);

        $readiness = Path::readiness($filePath);

        $this->assertFalse($readiness['exists']);
        $this->assertFalse($readiness['readable']);
        $this->assertFalse($readiness['writable']);
        $this->assertSame('not_directory', $readiness['reason']);
    }

    public function testProjectPathResolutionPrefersExistingApplicationDirectoryBeforeHostFallback(): void
    {
        $applicationRoot = Path::ensureDir(
            $this->tmpDir . DIRECTORY_SEPARATOR . 'application'
        );
        $projectRoot = Path::ensureDir(
            $this->tmpDir . DIRECTORY_SEPARATOR . 'project'
        );
        $applicationAddOns = Path::ensureDir(
            $applicationRoot . DIRECTORY_SEPARATOR . 'addons'
        );
        $resolver = new Func(
            static fn (string $path): string => Path::normalize(
                $projectRoot . DIRECTORY_SEPARATOR . $path
            )
        );

        $resolved = Path::resolveProjectPath(
            'addons',
            $applicationRoot,
            $projectRoot,
            $resolver
        );

        $this->assertSame(Path::normalize($applicationAddOns), $resolved);
    }

    public function testProjectPathResolutionPreservesHostFallbackWhenApplicationCandidateIsMissing(): void
    {
        $applicationRoot = Path::ensureDir(
            $this->tmpDir . DIRECTORY_SEPARATOR . 'application'
        );
        $projectRoot = Path::ensureDir(
            $this->tmpDir . DIRECTORY_SEPARATOR . 'project'
        );
        $customRoot = Path::ensureDir(
            $this->tmpDir . DIRECTORY_SEPARATOR . 'custom'
        );
        $resolver = new Func(
            static fn (string $path): string => Path::normalize(
                $customRoot . DIRECTORY_SEPARATOR . $path
            )
        );

        $resolved = Path::resolveProjectPath(
            'addons',
            $applicationRoot,
            $projectRoot,
            $resolver
        );

        $this->assertSame(
            Path::normalize($customRoot . DIRECTORY_SEPARATOR . 'addons'),
            $resolved
        );
    }

    public function testCanonicalizeResolvesExistingAndPendingPaths(): void
    {
        $root = Path::ensureDir($this->tmpDir . DIRECTORY_SEPARATOR . 'root');
        $existing = Path::ensureDir($root . DIRECTORY_SEPARATOR . 'existing');

        $existingResolution = Path::canonicalize($existing);
        $pendingResolution = Path::canonicalize('pending' . DIRECTORY_SEPARATOR . 'file.txt', $root);

        $this->assertTrue($existingResolution['resolved']);
        $this->assertTrue($existingResolution['exists']);
        $this->assertSame(Path::normalize(realpath($existing)), $existingResolution['canonicalPath']);
        $this->assertNull($existingResolution['reason']);

        $this->assertTrue($pendingResolution['resolved']);
        $this->assertFalse($pendingResolution['exists']);
        $this->assertSame(
            Path::normalize(realpath($root) . DIRECTORY_SEPARATOR . 'pending' . DIRECTORY_SEPARATOR . 'file.txt'),
            $pendingResolution['canonicalPath']
        );
        $this->assertNull($pendingResolution['reason']);
    }

    public function testCanonicalizeRejectsMalformedPaths(): void
    {
        $this->assertSame('invalid_path', Path::canonicalize("bad\0path")['reason']);
        $this->assertSame('invalid_path', Path::canonicalize('php://memory')['reason']);
        $this->assertSame('invalid_path', Path::canonicalize('*.php')['reason']);
        $this->assertSame('invalid_path', Path::canonicalize(null)['reason']);
    }

    public function testContainmentAcceptsRootExistingAndPendingDescendants(): void
    {
        $root = Path::ensureDir($this->tmpDir . DIRECTORY_SEPARATOR . 'root');
        $existing = File::ensureFile($root . DIRECTORY_SEPARATOR . 'existing.txt', 'data', true);

        $rootReceipt = Path::containment($root, $root);
        $existingReceipt = Path::containment($root, $existing);
        $pendingReceipt = Path::containment($root, 'pending' . DIRECTORY_SEPARATOR . 'file.txt');

        $this->assertTrue($rootReceipt['resolved']);
        $this->assertTrue($rootReceipt['contained']);
        $this->assertTrue($existingReceipt['contained']);
        $this->assertTrue($existingReceipt['candidateExists']);
        $this->assertTrue($pendingReceipt['contained']);
        $this->assertFalse($pendingReceipt['candidateExists']);
        $this->assertNull($pendingReceipt['reason']);
    }

    public function testContainmentRejectsTraversalPrefixCollisionsAndAbsoluteSubstitution(): void
    {
        $root = Path::ensureDir($this->tmpDir . DIRECTORY_SEPARATOR . 'root');
        $sibling = Path::ensureDir($this->tmpDir . DIRECTORY_SEPARATOR . 'root-sibling');
        $outside = File::ensureFile($sibling . DIRECTORY_SEPARATOR . 'outside.txt', 'data', true);

        $prefixCollision = Path::containment($root, $outside);
        $traversal = Path::containment($root, '..' . DIRECTORY_SEPARATOR . 'root-sibling' . DIRECTORY_SEPARATOR . 'outside.txt');

        $this->assertTrue($prefixCollision['resolved']);
        $this->assertFalse($prefixCollision['contained']);
        $this->assertSame('outside_root', $prefixCollision['reason']);
        $this->assertFalse($traversal['contained']);
        $this->assertSame('path_escape', $traversal['reason']);
    }

    public function testCanonicalizeRejectsForeignVolumeSyntax(): void
    {
        $foreign = DIRECTORY_SEPARATOR === '\\'
            ? '/outside.txt'
            : 'Z:\\outside.txt';

        $receipt = Path::canonicalize($foreign);

        $this->assertFalse($receipt['resolved']);
        $this->assertSame('different_volume', $receipt['reason']);
    }

    public function testContainmentRejectsPendingPathBelowFileAncestor(): void
    {
        $root = Path::ensureDir($this->tmpDir . DIRECTORY_SEPARATOR . 'root');
        File::ensureFile($root . DIRECTORY_SEPARATOR . 'file.txt', 'data', true);

        $receipt = Path::containment(
            $root,
            'file.txt' . DIRECTORY_SEPARATOR . 'child.txt'
        );

        $this->assertFalse($receipt['resolved']);
        $this->assertFalse($receipt['contained']);
        $this->assertSame('ancestor_not_directory', $receipt['reason']);
    }

    public function testContainmentResolvesLinksBeforeComparingBoundaries(): void
    {
        $root = Path::ensureDir($this->tmpDir . DIRECTORY_SEPARATOR . 'root');
        $outside = Path::ensureDir($this->tmpDir . DIRECTORY_SEPARATOR . 'outside');
        $link = $root . DIRECTORY_SEPARATOR . 'link';

        if (!function_exists('symlink') || !@symlink($outside, $link)) {
            $this->markTestSkipped('Symbolic-link creation is unavailable in this environment.');
        }

        $receipt = Path::containment($root, $link . DIRECTORY_SEPARATOR . 'pending.txt');

        $this->assertTrue($receipt['resolved']);
        $this->assertFalse($receipt['contained']);
        $this->assertSame('outside_root', $receipt['reason']);
    }

    public function testContainmentRejectsDifferentWindowsVolume(): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            $this->markTestSkipped('Windows volume semantics require Windows.');
        }

        $root = Path::ensureDir($this->tmpDir . DIRECTORY_SEPARATOR . 'root');
        $rootDrive = strtoupper(substr((string)realpath($root), 0, 1));
        $otherDrive = $rootDrive === 'Z' ? 'Y' : 'Z';

        $receipt = Path::containment($root, $otherDrive . ':\\outside.txt');

        $this->assertFalse($receipt['resolved']);
        $this->assertFalse($receipt['contained']);
        $this->assertSame('different_volume', $receipt['reason']);
    }

    public function testWindowsAmbiguousPendingSegmentsFailClosed(): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            $this->markTestSkipped('Win32 aliases require Windows.');
        }
        $root = Path::ensureDir($this->tmpDir . DIRECTORY_SEPARATOR . 'root');
        foreach (['.. /outside.txt', 'pending./file.txt', 'file:stream', 'NUL.txt', 'COM1', 'bad|name', 'C:'] as $path) {
            $receipt = Path::containment($root, $path);
            $this->assertFalse($receipt['resolved'], $path);
            $this->assertFalse($receipt['contained'], $path);
            $this->assertSame('invalid_path', $receipt['reason'], $path);
        }
    }

    public function testDanglingLinkCannotBeTreatedAsAPendingDirectory(): void
    {
        $root = Path::ensureDir($this->tmpDir . DIRECTORY_SEPARATOR . 'root');
        $link = $root . DIRECTORY_SEPARATOR . 'dangling';
        if (!function_exists('symlink') || !@symlink($this->tmpDir . '/absent', $link)) {
            $this->markTestSkipped('Symbolic-link creation is unavailable in this environment.');
        }
        $receipt = Path::containment($root, 'dangling/pending.txt');
        $this->assertFalse($receipt['resolved']);
        $this->assertFalse($receipt['contained']);
        $this->assertSame('resolution_unavailable', $receipt['reason']);
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
            if ($item->isLink()) {
                unlink($item->getPathname());
            } elseif ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
