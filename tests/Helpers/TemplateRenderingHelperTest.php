<?php

namespace BlueFission\Tests\Helpers;

use BlueFission\BlueCore\Engine;
use BlueFission\BlueCore\Theme;
use BlueFission\Services\Application;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use PHPUnit\Framework\TestCase;

class TemplateRenderingHelperTest extends TestCase
{
    private static string $root;

    public static function setUpBeforeClass(): void
    {
        self::$root = defined('APP_ROOT')
            ? APP_ROOT
            : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-template-rendering-tests';

        if (!defined('APP_ROOT')) {
            define('APP_ROOT', self::$root);
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
        $this->resetApplications();
        Path::ensureDir($this->themePath());
    }

    protected function tearDown(): void
    {
        $this->resetApplications();
    }

    public function testTemplateDelegatesToRegisteredRenderingService(): void
    {
        $renderer = new class {
            public array $arguments = [];

            public function render(string $theme, string $file, array $data): string
            {
                $this->arguments = [$theme, $file, $data];

                return 'service output';
            }
        };

        (new Engine())->delegate('template', $renderer);

        $this->assertSame('service output', template('admin', 'dashboard.vibe', ['title' => 'Dashboard']));
        $this->assertSame(['admin', 'dashboard.vibe', ['title' => 'Dashboard']], $renderer->arguments);
    }

    public function testTemplateFallsBackToDevelationParserRenderer(): void
    {
        $this->writeTemplate('welcome.tpl', 'Hello, {$name}!');
        $this->engineWithTheme();

        $this->assertSame('Hello, Ada!', template('test', 'welcome.tpl', ['name' => 'Ada']));
    }

    public function testTemplateFallsBackWhenServiceDoesNotExposeRender(): void
    {
        $this->writeTemplate('fallback.tpl', 'Fallback: {$value}');
        $engine = $this->engineWithTheme();
        $engine->delegate('template', new \stdClass());

        $this->assertSame('Fallback: ready', template('test', 'fallback.tpl', ['value' => 'ready']));
    }

    public function testTemplatePropagatesRendererExceptionsUnchanged(): void
    {
        $failure = new \RuntimeException('Renderer failed.');
        $renderer = new class ($failure) {
            public function __construct(private \RuntimeException $failure)
            {
            }

            public function render(string $theme, string $file, array $data): string
            {
                throw $this->failure;
            }
        };

        (new Engine())->delegate('template', $renderer);

        $this->expectExceptionObject($failure);
        template('test', 'failure.tpl');
    }

    public function testRecursiveServiceEntryUsesParserFallback(): void
    {
        $this->writeTemplate('recursive.tpl', 'Recursive: {$value}');
        $engine = $this->engineWithTheme();
        $engine->delegate('template', new class {
            public function render(string $theme, string $file, array $data): string
            {
                return template($theme, $file, $data);
            }
        });

        $this->assertSame('Recursive: guarded', template('test', 'recursive.tpl', ['value' => 'guarded']));
    }

    private function engineWithTheme(): Engine
    {
        $engine = new Engine();
        $engine->addTheme(new Theme('app/test', 'test'));

        return $engine;
    }

    private function writeTemplate(string $file, string $contents): void
    {
        File::ensureFile($this->themePath($file), $contents, true);
    }

    private function themePath(string $file = ''): string
    {
        return Path::normalize(
            self::$root
            . DIRECTORY_SEPARATOR . 'resource'
            . DIRECTORY_SEPARATOR . 'markup'
            . DIRECTORY_SEPARATOR . 'test'
            . (empty($file) ? '' : DIRECTORY_SEPARATOR . $file)
        );
    }

    private function resetApplications(): void
    {
        $instances = new \ReflectionProperty(Application::class, '_instances');
        $instances->setValue(null, []);
        $active = new \ReflectionProperty(Engine::class, '_activeInstances');
        $active->setValue(null, []);
    }
}
