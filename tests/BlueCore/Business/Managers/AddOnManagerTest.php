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
            ['addon_id' => 1, 'is_active' => 0],
            ['addon_id' => 2, 'is_active' => 1],
        ], returnArrays: true);
        $manager = new TestableAddOnManager($model);

        $result = $manager->activate(5);

        $this->assertSame('activate', $result['action']);
        $this->assertSame('5', $result['addon']);
        $this->assertSame('Success.', $result['modelStatus']);
        $this->assertSame(['addon_id' => 5, 'is_active' => 1], $model->writes[0]);

        $all = $manager->activateAll();

        $this->assertTrue($all['ok']);
        $this->assertSame('activate_all', $all['action']);
        $this->assertSame(1, $all['total']);
        $this->assertSame(1, $all['succeeded']);
        $this->assertSame(0, $all['failed']);
        $this->assertSame('1', $all['results'][0]['addon']);
    }

    public function testActivateAllPropagatesInnerFailuresAndMalformedRows(): void
    {
        $model = new FakeAddOnModel([
            ['addon_id' => 3, 'is_active' => 0],
            ['is_active' => 0],
        ], returnArrays: true, failWritesForIds: [3]);
        $manager = new TestableAddOnManager($model);

        $result = $manager->activateAll();

        $this->assertFalse($result['ok']);
        $this->assertSame(2, $result['total']);
        $this->assertSame(0, $result['succeeded']);
        $this->assertSame(2, $result['failed']);
        $this->assertSame('registration', $result['results'][0]['stage']);
        $this->assertSame('failed', $result['results'][1]['stage']);
        $this->assertSame('Persisted add-on row is missing addon_id.', $result['results'][1]['error']);
    }

    public function testRepeatedInstallUsesSupportedModelOperationsAndDoesNotDuplicateRegistration(): void
    {
        $this->writeDefinition('demo');
        $model = new FakeAddOnModel();
        $datasource = new FakeDatasourceManager();
        $manager = new TestableAddOnManager($model, $datasource);

        $first = $manager->install('demo');
        $second = $manager->install('demo');

        $this->assertTrue($first['changed']);
        $this->assertFalse($second['changed']);
        $this->assertSame('complete', $second['stage']);
        $this->assertCount(1, $model->records);
        $this->assertSame(1, $datasource->migrationRuns);
    }

    public function testFailedTeardownPreservesRegistrationForRetry(): void
    {
        $this->writeDefinition('demo');
        $path = self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'demo';
        $model = new FakeAddOnModel([
            (object)[
                'addon_id' => 7,
                'name' => 'demo',
                'path' => $path,
                'primary_file' => 'main.php',
                'is_active' => 1,
            ],
        ]);
        $datasource = new FakeDatasourceManager(failRollback: true);
        $manager = new TestableAddOnManager($model, $datasource);

        $result = $manager->uninstall(7);

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['changed']);
        $this->assertSame('datasource', $result['stage']);
        $this->assertSame('retry_uninstall', $result['nextAction']);
        $this->assertCount(1, $model->records);
        $this->assertSame([], $model->deletes);
    }

    public function testSuccessfulTeardownRemovesRegistrationLast(): void
    {
        $this->writeDefinition('demo');
        $path = self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'demo';
        $model = new FakeAddOnModel([
            (object)[
                'addon_id' => 8,
                'name' => 'demo',
                'path' => $path,
                'primary_file' => 'main.php',
                'is_active' => 1,
            ],
        ]);
        $manager = new TestableAddOnManager($model, new FakeDatasourceManager());

        $result = $manager->uninstall(8);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['changed']);
        $this->assertSame('complete', $result['stage']);
        $this->assertSame([['addon_id' => 8]], $model->deletes);
        $this->assertSame([], $model->records);
    }

    private function writeDefinition(string $name): void
    {
        File::ensureFile(
            self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'definition.json',
            json_encode([
                'name' => $name,
                'libraries' => [],
                'primary_file' => 'main.php',
            ]),
            true
        );
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
    public function __construct(
        FakeAddOnModel $model,
        private ?FakeDatasourceManager $datasource = null
    )
    {
        $this->_model = $model;
        $this->datasource ??= new FakeDatasourceManager();
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

    protected function datasourceManager(): mixed
    {
        return $this->datasource;
    }

    protected function callHook(\BlueFission\BlueCore\Domain\AddOn\AddOn $addOn, $hook): void
    {
    }
}

class FakeAddOnModel
{
    public array $writes = [];
    public array $deletes = [];
    public array $records = [];
    private array $current = [];
    private string $currentStatus = 'Success.';

    public function __construct(
        array $addons = [],
        private bool $returnArrays = false,
        private array $failWritesForIds = []
    )
    {
        $this->records = array_map(static fn($addon) => (array)$addon, $addons);
    }

    public function write($values): void
    {
        $record = (array)$values;
        $this->writes[] = $record;
        $id = $record['addon_id'] ?? count($this->records) + 1;
        $this->currentStatus = in_array($id, $this->failWritesForIds, true)
            ? 'Failed.'
            : 'Success.';

        if ($this->currentStatus !== 'Success.') {
            return;
        }

        $record['addon_id'] = $id;

        foreach ($this->records as $index => $existing) {
            if (($existing['addon_id'] ?? null) === $id) {
                $this->records[$index] = array_merge($existing, $record);
                $this->current = $this->records[$index];

                return;
            }
        }

        $this->records[] = $record;
        $this->current = $record;
    }

    public function clear(): self
    {
        $this->current = [];

        return $this;
    }

    public function read($values = null): self
    {
        $criteria = (array)($values ?? []);
        $this->current = [];

        foreach ($this->records as $record) {
            $matches = true;
            foreach ($criteria as $field => $value) {
                if (($record[$field] ?? null) !== $value) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $this->current = $record;
                break;
            }
        }

        return $this;
    }

    public function id(): mixed
    {
        return $this->current['addon_id'] ?? null;
    }

    public function data(): array
    {
        return $this->current;
    }

    public function all(): array
    {
        if ($this->returnArrays) {
            return $this->records;
        }

        return array_map(static fn($record) => (object)$record, $this->records);
    }

    public function delete($values): void
    {
        $criteria = (array)$values;
        $this->deletes[] = $criteria;
        $this->records = array_values(array_filter(
            $this->records,
            static fn($record) => ($record['addon_id'] ?? null) !== ($criteria['addon_id'] ?? null)
        ));
        $this->current = [];
    }

    public function status(): string
    {
        return $this->currentStatus;
    }

    public function query(): string
    {
        return 'UPDATE addons';
    }

}

class FakeDatasourceManager
{
    public int $migrationRuns = 0;

    public function __construct(private bool $failRollback = false)
    {
    }

    public function setDeltaDirectory(string $directory): void
    {
    }

    public function setGeneratorDirectory(string $directory): void
    {
    }

    public function runMigrations(string $batch): void
    {
        $this->migrationRuns++;
    }

    public function populate(): void
    {
    }

    public function revertBatch(string $batch): void
    {
        if ($this->failRollback) {
            throw new \RuntimeException('Datasource rollback failed.');
        }
    }
}
