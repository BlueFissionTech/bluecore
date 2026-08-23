<?php

namespace BlueFission\Tests\BlueCore\Business\Managers;

use BlueFission\BlueCore\Business\Managers\AddOnManager;
use BlueFission\BlueCore\Domain\AddOn\AddOn;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->assertSame('0.0.0', $definition->version);
        $this->assertSame('', $definition->namespace);
        $this->assertSame('main.php', $definition->primary_file);
        $this->assertSame(['bluefission/develation'], $definition->libraries);

        $this->expectException(\InvalidArgumentException::class);
        $manager->definition('missing');
    }

    #[DataProvider('invalidDefinitionProvider')]
    public function testDefinitionValidationIdentifiesInvalidFields(array $definition, string $field): void
    {
        $manager = new TestableAddOnManager(new FakeAddOnModel());
        File::ensureFile(
            self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'definition.json',
            json_encode($definition),
            true
        );

        try {
            $manager->definition('demo');
            $this->fail('Expected definition validation to fail.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString("field '{$field}'", $exception->getMessage());
        }
    }

    public static function invalidDefinitionProvider(): array
    {
        return [
            'mismatched name' => [['name' => 'other'], 'name'],
            'invalid version type' => [['name' => 'demo', 'version' => []], 'version'],
            'invalid namespace' => [['name' => 'demo', 'namespace' => 'Vendor\\Bad-Name'], 'namespace'],
            'escaping primary file' => [['name' => 'demo', 'primary_file' => '../main.php'], 'primary_file'],
            'invalid libraries type' => [['name' => 'demo', 'libraries' => 'vendor/package'], 'libraries'],
            'invalid library name' => [['name' => 'demo', 'libraries' => ['Bad Package']], 'libraries'],
        ];
    }

    public function testMalformedDefinitionAndUnsafeDirectoryFailBeforeLifecycleMutation(): void
    {
        $model = new FakeAddOnModel();
        $datasource = new FakeDatasourceManager();
        $manager = new TestableAddOnManager($model, $datasource);
        File::ensureFile(
            self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'definition.json',
            '{invalid',
            true
        );

        foreach (['demo', '../outside'] as $name) {
            try {
                $manager->install($name);
                $this->fail('Expected definition validation to fail.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('Invalid add-on definition', $exception->getMessage());
            }
        }

        $this->assertSame(0, $datasource->migrationRuns);
        $this->assertSame([], $model->writes);
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

    public function testNamespacedHookResolutionAndLegacyFallbackReturnDiagnostics(): void
    {
        $manager = new TestableAddOnManager(new FakeAddOnModel());
        $namespacedPath = self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'demo';
        File::ensureFile(
            $namespacedPath . DIRECTORY_SEPARATOR . 'main.php',
            "<?php\nnamespace BlueFission\\Tests\\Fixtures\\AddOnHooks;\nfunction demo_install() { \$GLOBALS['bluecore_hook_calls'][] = 'namespaced'; }",
            true
        );
        $namespaced = new AddOn();
        $namespaced->assign([
            'name' => 'demo',
            'namespace' => 'BlueFission\\Tests\\Fixtures\\AddOnHooks',
            'path' => $namespacedPath,
            'primary_file' => 'main.php',
        ]);

        $namespacedResult = $manager->hook($namespaced, 'install');

        $this->assertTrue($namespacedResult['ok']);
        $this->assertSame('namespaced', $namespacedResult['strategy']);
        $this->assertSame(
            'BlueFission\\Tests\\Fixtures\\AddOnHooks\\demo_install',
            $namespacedResult['callable']
        );

        $legacyPath = self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'legacydemo';
        File::ensureFile(
            $legacyPath . DIRECTORY_SEPARATOR . 'main.php',
            "<?php\nfunction legacydemo_install() { \$GLOBALS['bluecore_hook_calls'][] = 'legacy'; }",
            true
        );
        $legacy = new AddOn();
        $legacy->assign([
            'name' => 'legacydemo',
            'namespace' => 'Missing\\Namespace',
            'path' => $legacyPath,
            'primary_file' => 'main.php',
        ]);

        $legacyResult = $manager->hook($legacy, 'install');

        $this->assertTrue($legacyResult['ok']);
        $this->assertSame('legacy', $legacyResult['strategy']);
        $this->assertSame('legacydemo_install', $legacyResult['callable']);
        $this->assertSame(['namespaced', 'legacy'], $GLOBALS['bluecore_hook_calls']);
    }

    public function testMissingHookReturnsStructuredDiagnostics(): void
    {
        $manager = new TestableAddOnManager(new FakeAddOnModel());
        $path = self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'missing';
        File::ensureFile($path . DIRECTORY_SEPARATOR . 'main.php', '<?php', true);
        $addOn = new AddOn();
        $addOn->assign([
            'name' => 'missing',
            'namespace' => 'Vendor\\Package',
            'path' => $path,
            'primary_file' => 'main.php',
        ]);

        $result = $manager->hook($addOn, 'install');

        $this->assertFalse($result['ok']);
        $this->assertSame('missing_callable', $result['status']);
        $this->assertSame(
            ['Vendor\\Package\\missing_install', 'missing_install'],
            $result['attempted']
        );
        $this->assertNotEmpty($result['error']);
    }

    public function testPrimaryFilePrefersReachableConfiguredCandidate(): void
    {
        $manager = new TestableAddOnManager(new FakeAddOnModel());
        $addOnRoot = self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'configured';
        $configuredRoot = $addOnRoot . DIRECTORY_SEPARATOR . 'current';
        File::ensureFile($configuredRoot . DIRECTORY_SEPARATOR . 'main.php', '<?php', true);
        File::ensureFile($addOnRoot . DIRECTORY_SEPARATOR . 'main.php', '<?php', true);
        $addOn = new AddOn();
        $addOn->assign([
            'name' => 'configured',
            'path' => $configuredRoot,
            'primary_file' => 'main.php',
        ]);

        $resolution = $manager->primaryFile($addOn);

        $this->assertTrue($resolution['ok']);
        $this->assertSame('configured', $resolution['strategy']);
        $this->assertSame(Path::normalize($configuredRoot . DIRECTORY_SEPARATOR . 'main.php'), $resolution['path']);
    }

    public function testPrimaryFileFallsBackFromStalePersistedRoot(): void
    {
        $manager = new TestableAddOnManager(new FakeAddOnModel());
        $fallback = self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'portable' . DIRECTORY_SEPARATOR . 'main.php';
        File::ensureFile($fallback, '<?php', true);
        $addOn = new AddOn();
        $addOn->assign([
            'name' => 'portable',
            'path' => self::$root . DIRECTORY_SEPARATOR . 'retired-root' . DIRECTORY_SEPARATOR . 'portable',
            'primary_file' => 'main.php',
        ]);

        $resolution = $manager->primaryFile($addOn);

        $this->assertTrue($resolution['ok']);
        $this->assertSame('fallback', $resolution['strategy']);
        $this->assertSame(Path::normalize($fallback), $resolution['path']);
        $this->assertCount(2, $resolution['attempted']);
    }

    public function testPrimaryFileUsesCanonicalDefaultForLegacyRecord(): void
    {
        $manager = new TestableAddOnManager(new FakeAddOnModel());
        $fallback = self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'legacyrecord' . DIRECTORY_SEPARATOR . 'main.php';
        File::ensureFile($fallback, '<?php', true);
        $addOn = new AddOn();
        $addOn->assign([
            'name' => 'legacyrecord',
            'path' => '',
            'primary_file' => '',
        ]);

        $resolution = $manager->primaryFile($addOn);

        $this->assertTrue($resolution['ok']);
        $this->assertSame('fallback', $resolution['strategy']);
        $this->assertNull($resolution['configured']);
        $this->assertSame(Path::normalize($fallback), $resolution['path']);
    }

    public function testPrimaryFileRejectsReachablePathOutsideAddOnRoot(): void
    {
        $manager = new TestableAddOnManager(new FakeAddOnModel());
        $outside = self::$root . DIRECTORY_SEPARATOR . 'outside' . DIRECTORY_SEPARATOR . 'main.php';
        File::ensureFile($outside, '<?php', true);
        File::ensureFile(
            self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'contained' . DIRECTORY_SEPARATOR . 'main.php',
            '<?php',
            true
        );
        $addOn = new AddOn();
        $addOn->assign([
            'name' => 'contained',
            'path' => dirname($outside),
            'primary_file' => 'main.php',
        ]);

        $resolution = $manager->primaryFile($addOn);

        $this->assertFalse($resolution['ok']);
        $this->assertSame('unsafe_primary_file', $resolution['status']);
        $this->assertNull($resolution['path']);
        $this->assertNotEmpty($resolution['error']);
    }

    public function testPrimaryFileRejectsTraversalAndReportsMissingCandidates(): void
    {
        $manager = new TestableAddOnManager(new FakeAddOnModel());
        Path::ensureDir(self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'unsafe');
        $unsafe = new AddOn();
        $unsafe->assign([
            'name' => 'unsafe',
            'path' => self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'unsafe',
            'primary_file' => '../main.php',
        ]);

        $unsafeResolution = $manager->primaryFile($unsafe);

        $this->assertFalse($unsafeResolution['ok']);
        $this->assertSame('unsafe_primary_file', $unsafeResolution['status']);

        $missing = new AddOn();
        $missing->assign([
            'name' => 'missing-primary',
            'path' => self::$root . DIRECTORY_SEPARATOR . 'retired-root' . DIRECTORY_SEPARATOR . 'missing-primary',
            'primary_file' => 'main.php',
        ]);
        Path::ensureDir(self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'missing-primary');

        $missingResolution = $manager->primaryFile($missing);

        $this->assertFalse($missingResolution['ok']);
        $this->assertSame('missing_primary_file', $missingResolution['status']);
        $this->assertCount(2, $missingResolution['attempted']);
        $this->assertSame([
            $missingResolution['configured'],
            $missingResolution['fallback'],
        ], $missingResolution['attempted']);
    }

    public function testHookAndLoaderSharePortablePrimaryFileResolution(): void
    {
        $manager = new TestableAddOnManager(new FakeAddOnModel());
        $fallback = self::$root . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'sharedresolver' . DIRECTORY_SEPARATOR . 'main.php';
        File::ensureFile(
            $fallback,
            "<?php\n\$GLOBALS['bluecore_primary_loads'][] = 'loaded';\nfunction sharedresolver_install() { \$GLOBALS['bluecore_hook_calls'][] = 'portable'; }",
            true
        );
        $addOn = new AddOn();
        $addOn->assign([
            'name' => 'sharedresolver',
            'path' => self::$root . DIRECTORY_SEPARATOR . 'retired-root' . DIRECTORY_SEPARATOR . 'sharedresolver',
            'primary_file' => 'main.php',
        ]);

        $load = $manager->load($addOn);
        $hook = $manager->hook($addOn, 'install');

        $this->assertTrue($load['ok']);
        $this->assertSame('fallback', $load['strategy']);
        $this->assertTrue($hook['ok']);
        $this->assertSame(Path::normalize($fallback), $hook['primaryFile']);
        $this->assertSame(['loaded'], $GLOBALS['bluecore_primary_loads']);
        $this->assertContains('portable', $GLOBALS['bluecore_hook_calls']);
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

    public function testInstalledAddOnAppliesOnlyPendingMigrationsAndRepeatsAsNoOp(): void
    {
        $this->writeDefinition('demo');
        $model = new FakeAddOnModel();
        $datasource = new FakeDatasourceManager();
        $datasource->publish('001_initial.php');
        $manager = new TestableAddOnManager($model, $datasource);

        $install = $manager->install('demo');
        $datasource->publish('002_later.php');
        $refresh = $manager->migrate($model->records[0]['addon_id']);
        $repeat = $manager->migrate($model->records[0]['addon_id']);

        $this->assertTrue($install['ok']);
        $this->assertSame(1, $install['migrations']['applied']);
        $this->assertTrue($refresh['ok']);
        $this->assertTrue($refresh['changed']);
        $this->assertSame('complete', $refresh['stage']);
        $this->assertSame('002_later.php', $refresh['migrations']['results'][0]['name']);
        $this->assertTrue($repeat['ok']);
        $this->assertFalse($repeat['changed']);
        $this->assertSame(0, $repeat['migrations']['total']);
        $this->assertSame(1, $datasource->populationRuns);
    }

    public function testMigrationHistoryIsScopedToEachInstalledAddOn(): void
    {
        $this->writeDefinition('first');
        $this->writeDefinition('second');
        $datasource = new FakeDatasourceManager();
        $datasource->publish('001_shared.php');
        $manager = new TestableAddOnManager(new FakeAddOnModel(), $datasource);

        $first = $manager->install('first');
        $second = $manager->install('second');

        $this->assertSame(1, $first['migrations']['applied']);
        $this->assertSame(1, $second['migrations']['applied']);
        $this->assertSame(['001_shared.php'], $datasource->appliedFor('first'));
        $this->assertSame(['001_shared.php'], $datasource->appliedFor('second'));
    }

    public function testMigrationFailurePreventsSuccessAndRemainsRetryable(): void
    {
        $this->writeDefinition('demo');
        $model = new FakeAddOnModel();
        $datasource = new FakeDatasourceManager();
        $datasource->publish('001_initial.php');
        $manager = new TestableAddOnManager($model, $datasource);
        $manager->install('demo');
        $datasource->publish('002_retry.php');
        $datasource->failOn('002_retry.php');

        $failed = $manager->migrate($model->records[0]['addon_id']);
        $retried = $manager->migrate($model->records[0]['addon_id']);

        $this->assertFalse($failed['ok']);
        $this->assertFalse($failed['changed']);
        $this->assertSame('datasource', $failed['stage']);
        $this->assertSame('retry_migrate', $failed['nextAction']);
        $this->assertSame('Migration 002_retry.php failed.', $failed['error']);
        $this->assertFalse($retried['ok']);
        $this->assertSame('002_retry.php', $retried['migrations']['results'][0]['name']);
        $this->assertSame(['001_initial.php'], $datasource->appliedFor('demo'));
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

    public function hook(AddOn $addOn, string $hook): array
    {
        return $this->callHook($addOn, $hook);
    }

    public function primaryFile(AddOn $addOn): array
    {
        return $this->resolvePrimaryFile($addOn);
    }

    public function load(AddOn $addOn): array
    {
        return $this->loadAddOn($addOn);
    }

    protected function datasourceManager(): mixed
    {
        return $this->datasource;
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
    public int $populationRuns = 0;
    private array $published = [];
    private array $applied = [];
    private ?string $failedMigration = null;

    public function __construct(private bool $failRollback = false)
    {
    }

    public function setDeltaDirectory(string $directory): void
    {
    }

    public function setGeneratorDirectory(string $directory): void
    {
    }

    public function publish(string $migration): void
    {
        if (!\BlueFission\Arr::has($this->published, $migration, true)) {
            $this->published[] = $migration;
        }
    }

    public function failOn(?string $migration): void
    {
        $this->failedMigration = $migration;
    }

    public function appliedFor(string $batch): array
    {
        return $this->applied[$batch] ?? [];
    }

    public function runMigrations(string $batch): array
    {
        $this->migrationRuns++;
        $applied = $this->applied[$batch] ?? [];
        $pending = \BlueFission\Arr::make($this->published)
            ->diff($applied)
            ->values()
            ->toArray();
        $results = [];

        foreach ($pending as $migration) {
            $ok = $migration !== $this->failedMigration;
            $results[] = [
                'ok' => $ok,
                'name' => $migration,
                'status' => $ok ? 'applied' : 'failed',
                'error' => $ok ? null : "Migration {$migration} failed.",
            ];

            if (!$ok) {
                return [
                    'ok' => false,
                    'batch' => $batch,
                    'changed' => false,
                    'total' => \BlueFission\Arr::size($pending),
                    'applied' => 0,
                    'failed' => 1,
                    'results' => $results,
                    'nextAction' => 'retry_migrations',
                    'error' => "Migration {$migration} failed.",
                ];
            }

            $this->applied[$batch][] = $migration;
        }

        return [
            'ok' => true,
            'batch' => $batch,
            'changed' => \BlueFission\Arr::isNotEmpty($pending),
            'total' => \BlueFission\Arr::size($pending),
            'applied' => \BlueFission\Arr::size($pending),
            'failed' => 0,
            'results' => $results,
            'nextAction' => null,
            'error' => null,
        ];
    }

    public function populate(): void
    {
        $this->populationRuns++;
    }

    public function revertBatch(string $batch): void
    {
        if ($this->failRollback) {
            throw new \RuntimeException('Datasource rollback failed.');
        }
    }
}
