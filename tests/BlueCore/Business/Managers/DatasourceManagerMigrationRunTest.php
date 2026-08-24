<?php

namespace BlueFission\Tests\BlueCore\Business\Managers;

use BlueFission\Arr;
use BlueFission\BlueCore\Business\Managers\DatasourceManager;
use PHPUnit\Framework\TestCase;

class DatasourceManagerMigrationRunTest extends TestCase
{
    public function testPendingMigrationsAreScopedByBatchAndRepeatAsNoOp(): void
    {
        $manager = new ExecutableDatasourceManager(['001_initial.php', '002_later.php']);

        $first = $manager->runMigrations('first-addon');
        $repeat = $manager->runMigrations('first-addon');
        $other = $manager->runMigrations('second-addon');

        $this->assertTrue($first['ok']);
        $this->assertTrue($first['changed']);
        $this->assertSame(2, $first['applied']);
        $this->assertSame(['001_initial.php', '002_later.php'], $manager->historyFor('first-addon'));
        $this->assertTrue($repeat['ok']);
        $this->assertFalse($repeat['changed']);
        $this->assertSame(0, $repeat['total']);
        $this->assertSame(2, $other['applied']);
        $this->assertSame(['001_initial.php', '002_later.php'], $manager->historyFor('second-addon'));
    }

    public function testFailedMigrationStopsTheBatchAndRemainsRetryable(): void
    {
        $manager = new ExecutableDatasourceManager(['001_initial.php', '002_retry.php', '003_after.php']);
        $manager->failOn('002_retry.php');

        $failed = $manager->runMigrations('retry-addon');
        $historyAfterFailure = $manager->historyFor('retry-addon');
        $manager->failOn(null);
        $retried = $manager->runMigrations('retry-addon');

        $this->assertFalse($failed['ok']);
        $this->assertSame('migration', $failed['stage']);
        $this->assertSame(1, $failed['applied']);
        $this->assertSame(1, $failed['failed']);
        $this->assertSame('retry_migrations', $failed['nextAction']);
        $this->assertSame('002_retry.php failed', $failed['error']);
        $this->assertSame(['001_initial.php'], $historyAfterFailure);
        $this->assertTrue($retried['ok']);
        $this->assertSame(2, $retried['applied']);
        $this->assertSame(
            ['001_initial.php', '002_retry.php', '003_after.php'],
            $manager->historyFor('retry-addon')
        );
    }
}

class ExecutableDatasourceManager extends DatasourceManager
{
    private array $history = [];
    private ?string $failedMigration = null;

    public function __construct(private array $available)
    {
        $this->_deltaDir = 'virtual' . DIRECTORY_SEPARATOR;
        $this->_db = new MigrationRunStorage(function (array $record): void {
            if ((int)Arr::getPath($record, 'status', 0) !== 2) {
                return;
            }

            $batch = (string)Arr::getPath($record, 'batch', '');
            $name = (string)Arr::getPath($record, 'name', '');
            if (!Arr::has($this->history[$batch] ?? [], $name, true)) {
                $this->history[$batch][] = $name;
            }
        });
    }

    public function failOn(?string $migration): void
    {
        $this->failedMigration = $migration;
    }

    public function historyFor(string $batch): array
    {
        return $this->history[$batch] ?? [];
    }

    protected function deltaDirectoryExists(): bool
    {
        return true;
    }

    protected function migrationTableExists(): bool
    {
        return true;
    }

    protected function loadDeltas($reverse = SCANDIR_SORT_ASCENDING): array
    {
        return Arr::make(['.', '..'])->merge($this->available)->toArray();
    }

    protected function getDeltasFromDB(&$iteration, ?string $batch = null): array
    {
        $iteration = Arr::isNotEmpty($this->historyFor((string)$batch)) ? 1 : 0;

        return $this->historyFor((string)$batch);
    }

    protected function findClassName($file): ?string
    {
        return basename((string)$file);
    }

    protected function includeMigration(string $path): void
    {
    }

    protected function migrationInstance(string $classname): object
    {
        return new TestMigration($classname, $this->failedMigration === $classname);
    }
}

class TestMigration
{
    public function __construct(private string $name, private bool $fails)
    {
    }

    public function change(): void
    {
        if ($this->fails) {
            throw new \RuntimeException("{$this->name} failed");
        }
    }
}

class MigrationRunStorage
{
    private array $record = [];
    private int $lastRow = 0;

    public function __construct(private $onWrite)
    {
    }

    public function clear(): self
    {
        $this->record = [];

        return $this;
    }

    public function assign(array $record): self
    {
        $this->record = $record;

        return $this;
    }

    public function write(): self
    {
        $this->lastRow++;
        ($this->onWrite)($this->record);

        return $this;
    }

    public function id(mixed $id): self
    {
        return $this;
    }

    public function lastRow(): int
    {
        return $this->lastRow;
    }

    public function activate(): self
    {
        return $this;
    }
}
