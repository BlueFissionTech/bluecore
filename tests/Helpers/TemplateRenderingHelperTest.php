<?php

namespace BlueFission\Tests\Helpers;

use BlueFission\Arr;
use BlueFission\BlueCore\Engine;
use BlueFission\BlueCore\Hooks\HelperLifecycleHooks;
use BlueFission\BlueCore\Hooks\LifecycleFailure;
use BlueFission\BlueCore\Theme;
use BlueFission\DevElation as Dev;
use BlueFission\Services\Application;
use BlueFission\Str;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

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
        $this->resetDevElation();
        Path::ensureDir($this->themePath());
    }

    protected function tearDown(): void
    {
        $this->resetDevElation();
        $this->resetApplications();
    }

    public function testTemplateFiltersDataAndOutputAroundRenderingActions(): void
    {
        $events = [];
        $this->writeTemplate('hooked.tpl', 'Hello, {$name}');
        $this->engineWithTheme();
        Dev::up();
        Dev::filter(HelperLifecycleHooks::FILTER_TEMPLATE_DATA, function (Arr $data) use (&$events): Arr {
            $events[] = 'data';
            $data->set('name', 'Grace');

            return $data;
        });
        Dev::action(HelperLifecycleHooks::HOOK_TEMPLATE_BEFORE, function (Arr $summary) use (&$events): void {
            $events[] = 'before';
            $this->assertSame(1, $summary['data_count']);
            $this->assertFalse($summary->hasKey('data'));
        });
        Dev::filter(HelperLifecycleHooks::FILTER_TEMPLATE_OUTPUT, function (Str $output) use (&$events): Str {
            $events[] = 'output';

            return $output->append('!');
        });
        Dev::action(HelperLifecycleHooks::HOOK_TEMPLATE_AFTER, function (Arr $summary) use (&$events): void {
            $events[] = 'after';
            $this->assertSame(13, $summary['output_length']);
        });

        $this->assertSame('Hello, Grace!', template('test', 'hooked.tpl', ['name' => 'Ada']));
        $this->assertSame(['data', 'before', 'output', 'after'], $events);
    }

    public function testTemplateOutputFilterRejectsInvalidTypesAndDispatchesFailure(): void
    {
        $failure = null;
        $this->writeTemplate('invalid-hook.tpl', 'Invalid');
        $this->engineWithTheme();
        Dev::up();
        Dev::filter(HelperLifecycleHooks::FILTER_TEMPLATE_OUTPUT, fn(Str $output): string => $output->val());
        Dev::action(
            HelperLifecycleHooks::HOOK_TEMPLATE_FAILED,
            function (LifecycleFailure $context) use (&$failure): void {
                $failure = $context;
            }
        );

        try {
            template('test', 'invalid-hook.tpl');
            $this->fail('The invalid template output filter did not fail.');
        } catch (UnexpectedValueException $exception) {
            $this->assertSame('Template output filters must return Str.', $exception->getMessage());
        }

        $this->assertInstanceOf(LifecycleFailure::class, $failure);
        $this->assertSame(HelperLifecycleHooks::HOOK_TEMPLATE_FAILED, $failure->hook());
        $this->assertSame(UnexpectedValueException::class, $failure->exceptionType());
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

    private function resetDevElation(): void
    {
        Dev::down();

        foreach (['_filters', '_actions', '_listeners', '_config'] as $propertyName) {
            $property = new \ReflectionProperty(Dev::class, $propertyName);
            $property->setValue(null, []);
        }
    }
}
