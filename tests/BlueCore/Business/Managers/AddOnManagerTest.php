<?php

namespace BlueFission\Tests\BlueCore\Business\Managers;

use BlueFission\BlueCore\Business\Managers\AddOnManager;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use PHPUnit\Framework\TestCase;

class AddOnManagerTest extends TestCase
{
    private static string $root;
    private static bool $ownsRoot = false;

    public static function setUpBeforeClass(): void
    {
        self::$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-addon-manager-tests';

        if (!defined('APP_ROOT')) {
            define('APP_ROOT', self::$root);
            self::$ownsRoot = true;
        } else {
            self::$root = APP_ROOT;
        }

        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', self::$root);
        }
    }

    protected function setUp(): void
    {
        if (self::$ownsRoot) {
            $this->removeDir(self::$root);
        }

        Path::ensureDir(self::$root . DIRECTORY_SEPARATOR . 'addons');
    }

    protected function tearDown(): void
    {
        if (self::$ownsRoot) {
            $this->removeDir(self::$root);
        }
    }

    public function testDependencyCommandsAreSanitizedAndExplicit(): void
    {
        $manager = new TestableAddOnManager(new FakeAddOnModel());

        $commands = $manager->commands([
            'bluefission/develation',
            'Bad Package',
            'vendor/package;rm',
            'bluefission/opus-framework',
        ], 'require');

        $this->assertSame([
            'composer require bluefission/develation',
            'composer require bluefission/opus-framework',
        ], $commands);
    }

    public function testDefinitionLoadingAppliesDefaultsAndValidatesMissingFile(): void
    {
        $manager = new TestableAddOnManager(new FakeAddOnModel());
        $definitionDir = self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'demo';
        File::ensureFile(
            $definitionDir . DIRECTORY_SEPARATOR . 'definition.json',
            json_encode([
                'description' => 'Demo add-on',
                'libraries' => ['bluefission/develation'],
            ]),
            true
        );

        $definition = $manager->definition('demo');

        $this->assertSame('demo', $definition->name);
        $this->assertSame('main.php', $definition->primary_file);
        $this->assertSame(['bluefission/develation'], $definition->libraries);

        $this->expectException(\InvalidArgumentException::class);
        $manager->definition('missing');
    }

    public function testAddOnPathsArePortableAndRemainWithinTheConfiguredRoot(): void
    {
        $manager = new TestableAddOnManager(new FakeAddOnModel());
        $definitionDir = self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'demo';
        Path::ensureDir($definitionDir);

        $path = $manager->path('demo');

        $this->assertStringNotContainsString('\\', $path);
        $this->assertStringEndsWith('/addons/demo', $path);

        Path::ensureDir(self::$root . DIRECTORY_SEPARATOR . 'outside');

        $this->expectException(\InvalidArgumentException::class);
        $manager->path('../outside');
    }

    public function testActivateAndActivateAllReturnStructuredStatuses(): void
    {
        $model = new FakeAddOnModel([
            (object)['addon_id' => 1, 'is_active' => 0],
            (object)['addon_id' => 2, 'is_active' => 1],
        ]);
        $manager = new TestableAddOnManager($model);

        $result = $manager->activate(5);

        $this->assertSame('activate', $result['action']);
        $this->assertSame('5', $result['addon']);
        $this->assertSame('Success.', $result['modelStatus']);
        $this->assertSame(['addon_id' => 5, 'is_active' => 1], $model->writes[0]);

        $all = $manager->activateAll();

        $this->assertCount(1, $all);
        $this->assertSame('1', $all[0]['addon']);
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

class TestableAddOnManager extends AddOnManager
{
    public function __construct(FakeAddOnModel $model)
    {
        $this->_model = $model;
    }

    public function commands(array $libraries, string $operation): array
    {
        return $this->dependencyCommands($libraries, $operation);
    }

    public function definition(string $name): object
    {
        return $this->getAddOnData($name);
    }

    public function path(string $name): string
    {
        return $this->addOnPath($name);
    }
}

class FakeAddOnModel
{
    public array $writes = [];

    public function __construct(private array $addons = [])
    {
    }

    public function write($values): void
    {
        $this->writes[] = (array)$values;
    }

    public function status(): string
    {
        return 'Success.';
    }

    public function query(): string
    {
        return 'UPDATE addons';
    }

    public function getAllAddOns(): array
    {
        return $this->addons;
    }
}
