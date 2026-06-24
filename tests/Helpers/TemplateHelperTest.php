<?php

namespace BlueFission\Tests\Helpers;

use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use PHPUnit\Framework\TestCase;

class TemplateHelperTest extends TestCase
{
    private static string $root;
    private static bool $ownsRoot = false;

    public static function setUpBeforeClass(): void
    {
        self::$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-template-helper-tests';

        if (!defined('APP_ROOT')) {
            define('APP_ROOT', self::$root);
            self::$ownsRoot = true;
        } else {
            self::$root = APP_ROOT;
        }

        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', self::$root);
        }

        if (!defined('SITE_ROOT')) {
            define('SITE_ROOT', self::$root);
        }
    }

    protected function setUp(): void
    {
        if (self::$ownsRoot) {
            $this->removeDir(self::$root);
        }

        Path::ensureDir(self::$root . DIRECTORY_SEPARATOR . 'resource' . DIRECTORY_SEPARATOR . 'markup' . DIRECTORY_SEPARATOR . 'custom');
    }

    protected function tearDown(): void
    {
        if (self::$ownsRoot) {
            $this->removeDir(self::$root);
        }
    }

    public function testTemplatePathPrefersCustomOverride(): void
    {
        $markup = $this->markupPath('page.php');
        $custom = $this->markupPath('custom' . DIRECTORY_SEPARATOR . 'page.php');
        File::ensureFile($markup, 'base', true);
        File::ensureFile($custom, 'custom', true);

        $this->assertSame($custom, get_template_path('page.php'));
    }

    public function testTemplatePathUsesMarkupBaseWhenCallerIsInsideCustomDirectory(): void
    {
        $partial = $this->markupPath('partial.php');
        $caller = $this->markupPath('custom' . DIRECTORY_SEPARATOR . 'caller.php');
        File::ensureFile($partial, 'partial', true);
        File::ensureFile($caller, "<?php return get_template_path('partial.php');", true);

        $this->assertSame($partial, include $caller);
    }

    public function testTemplatePathRejectsUnsafeTemplateNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        get_template_path('../outside.php');
    }

    public function testTemplatePathRejectsAbsoluteTemplateNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        get_template_path(self::$root . DIRECTORY_SEPARATOR . 'resource' . DIRECTORY_SEPARATOR . 'markup' . DIRECTORY_SEPARATOR . 'page.php');
    }

    public function testTemplateUrlNormalizesToSiteRelativePath(): void
    {
        $custom = $this->markupPath('custom' . DIRECTORY_SEPARATOR . 'asset.php');
        File::ensureFile($custom, 'asset', true);

        $this->assertSame('/resource/markup/custom/asset.php', get_template_url('asset.php'));
    }

    private function markupPath(string $path): string
    {
        return Path::normalize(self::$root . DIRECTORY_SEPARATOR . 'resource' . DIRECTORY_SEPARATOR . 'markup' . DIRECTORY_SEPARATOR . $path);
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
