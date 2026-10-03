<?php

namespace BlueFission\Tests\BlueCore\Business\Managers;

use BlueFission\BlueCore\Business\Managers\DatasourceManager;
use BlueFission\Data\Storage\Storage;
use PHPUnit\Framework\TestCase;

class DatasourceManagerRevertTest extends TestCase
{
    private function record(string $name, int $iteration = 1, string $batch = 'core', int $id = 1, int $status = 2): array
    {
        return ['name' => $name, 'batch' => $batch, 'iteration' => $iteration,
            'migration_id' => $id, 'status' => $status];
    }

    public function testLatestIterationRevertsInReverseOrderAndPreservesOlderHistory(): void
    {
        $older = $this->record('older.php');
        $manager = new RevertTestManager([$older,
            $this->record('first.php', 2, 'core', 2),
            $this->record('last.php', 2, 'core', 3)]);

        $result = $manager->revertMigrations();

        $this->assertTrue($result['ok']);
        $this->assertSame('revert_migrations', $result['operation']);
        $this->assertSame(2, $result['iteration']);
        $this->assertSame(2, $result['reverted']);
        $this->assertSame(['last.php', 'first.php'], $manager->calls);
        $this->assertSame([$older], $manager->storage->rows);
        $this->assertTrue($result['results'][0]['historyRemoved']);
    }

    public function testBatchRevertsAcrossIterationsWithoutDeletingOtherBatchesOrFailedHistory(): void
    {
        $other = $this->record('shared.php', 2, 'other', 3);
        $failed = $this->record('failed.php', 2, 'core', 4, 3);
        $manager = new RevertTestManager([
            $this->record('shared.php', 1), $this->record('shared.php', 2, 'core', 2), $other, $failed]);

        $result = $manager->revertBatch('core');

        $this->assertTrue($result['ok']);
        $this->assertSame('core', $result['batch']);
        $this->assertSame([2, 1], array_column($result['results'], 'iteration'));
        $this->assertSame([$other, $failed], $manager->storage->rows);
        $this->assertSame([2, 1], array_column($manager->storage->deleted, 'iteration'));
    }

    public function testErrorRetainsFailedAndUnattemptedHistoryAfterPartialSuccess(): void
    {
        $first = $this->record('first.php');
        $broken = $this->record('broken.php', 1, 'core', 2);
        $manager = new RevertTestManager([$first, $broken, $this->record('last.php', 1, 'core', 3)]);
        $manager->failOn = 'broken.php';

        $result = $manager->revertBatch('core');

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['changed']);
        $this->assertSame('partial', $result['stage']);
        $this->assertSame(3, $result['total']);
        $this->assertSame(1, $result['reverted']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(1, $result['unattempted']);
        $this->assertSame(['reverted', 'failed', 'not_attempted'], array_column($result['results'], 'status'));
        $this->assertSame(\Error::class, $result['results'][1]['exception']);
        $this->assertSame('broken.php revert failed', $result['error']);
        $this->assertSame([$first, $broken], $manager->storage->rows);
        $this->assertSame(['last.php', 'broken.php'], $manager->calls);
    }

    public function testFirstFailureReportsNoConfirmedChange(): void
    {
        $row = $this->record('broken.php');
        $manager = new RevertTestManager([$row]);
        $manager->failOn = 'broken.php';
        $result = $manager->revertMigrations();
        $this->assertFalse($result['ok']);
        $this->assertFalse($result['changed']);
        $this->assertSame('revert', $result['stage']);
        $this->assertSame('review_revert_failure', $result['nextAction']);
        $this->assertSame([$row], $manager->storage->rows);
    }

    public function testMissingClassRetainsHistory(): void
    {
        $row = $this->record('missing.php');
        $manager = new RevertTestManager([$row]);
        $manager->missingClass = true;
        $result = $manager->revertBatch('core');
        $this->assertFalse($result['ok']);
        $this->assertSame('missing_class', $result['results'][0]['status']);
        $this->assertSame(\RuntimeException::class, $result['results'][0]['exception']);
        $this->assertSame([], $manager->calls);
        $this->assertSame([$row], $manager->storage->rows);
    }

    public function testIncludeErrorsBecomeReceiptsWithoutDeletingHistory(): void
    {
        $row = $this->record('invalid.php');
        $manager = new RevertTestManager([$row]);
        $manager->includeFails = true;
        $result = $manager->revertBatch('core');
        $this->assertFalse($result['ok']);
        $this->assertSame(\ParseError::class, $result['exception']);
        $this->assertSame([$row], $manager->storage->rows);
    }

    public function testCleanupFailureRequiresReconciliationRatherThanBlindReplay(): void
    {
        $row = $this->record('last.php');
        $manager = new RevertTestManager([$row]);
        $manager->storage->deleteFails = true;
        $result = $manager->revertBatch('core');
        $this->assertFalse($result['ok']);
        $this->assertTrue($result['changed']);
        $this->assertSame(1, $result['reverted']);
        $this->assertSame('history_cleanup_failed', $result['results'][0]['status']);
        $this->assertFalse($result['results'][0]['historyRemoved']);
        $this->assertSame('reconcile_migration_history', $result['nextAction']);
        $this->assertSame([$row], $manager->storage->rows);
    }

    public function testMissingDirectoryIsUnavailableAndDoesNotRunMigrations(): void
    {
        $row = $this->record('last.php');
        $manager = new RevertTestManager([$row]);
        $manager->directoryExists = false;
        $result = $manager->revertBatch('core');
        $this->assertFalse($result['ok']);
        $this->assertSame('unavailable', $result['stage']);
        $this->assertSame('missing_directory', $result['reason']);
        $this->assertSame('restore_migration_directory', $result['nextAction']);
        $this->assertSame([], $manager->calls);
        $this->assertSame([$row], $manager->storage->rows);
    }

    public function testAbsentTableAndEmptyHistoryAreExplicitNoOps(): void
    {
        $manager = new RevertTestManager([]);
        $empty = $manager->revertBatch('core');
        $manager->tableExists = false;
        $manager->directoryExists = false;
        $absent = $manager->revertMigrations();
        $this->assertTrue($empty['ok']);
        $this->assertSame('empty_history', $empty['reason']);
        $this->assertTrue($absent['ok']);
        $this->assertSame('no_history', $absent['reason']);
        $this->assertFalse($absent['changed']);
        $this->assertSame([], $manager->calls);
    }

    public function testHistoryReadFailureIsNotReportedAsAnEmptyBatch(): void
    {
        $manager = new RevertTestManager([]);
        $manager->storage->readFails = true;
        $result = $manager->revertBatch('core');
        $this->assertFalse($result['ok']);
        $this->assertSame('discovery', $result['stage']);
        $this->assertSame('review_migration_history', $result['nextAction']);
        $this->assertSame('Migration history could not be read.', $result['error']);
    }

    public function testBlankBatchCannotRevertEveryBatch(): void
    {
        $manager = new RevertTestManager([$this->record('last.php')]);
        $result = $manager->revertBatch(' ');
        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_batch', $result['reason']);
        $this->assertSame([], $manager->calls);
    }

    public function testMalformedHistoryFailsBeforeAnyRevert(): void
    {
        $manager = new RevertTestManager([$this->record('../outside.php')]);
        $result = $manager->revertMigrations();
        $this->assertFalse($result['ok']);
        $this->assertSame(\UnexpectedValueException::class, $result['exception']);
        $this->assertSame([], $manager->calls);
        $this->assertSame([], $manager->storage->deleted);
    }
}

class RevertTestManager extends DatasourceManager
{
    public RevertTestStorage $storage;
    public array $calls = [];
    public bool $tableExists = true;
    public bool $directoryExists = true;
    public bool $missingClass = false;
    public bool $includeFails = false;
    public ?string $failOn = null;

    public function __construct(array $rows)
    {
        $this->_deltaDir = 'virtual/';
        $this->_db = $this->storage = new RevertTestStorage($rows);
    }
    protected function migrationTableExists(): bool { return $this->tableExists; }
    protected function deltaDirectoryExists(): bool { return $this->directoryExists; }
    protected function findClassName($file): ?string { return $this->missingClass ? null : basename($file); }
    protected function includeMigration(string $path): void
    {
        if ($this->includeFails) { throw new \ParseError('Invalid migration.'); }
    }
    protected function migrationInstance(string $classname): object
    {
        return new RevertTestMigration(function () use ($classname): void {
            $this->calls[] = $classname;
            if ($this->failOn === $classname) { throw new \Error("{$classname} revert failed"); }
        });
    }
}

class RevertTestMigration
{
    public function __construct(private \Closure $action) {}
    public function revert(): void { ($this->action)(); }
}

class RevertTestStorage
{
    public array $deleted = [];
    public bool $readFails = false;
    public bool $deleteFails = false;
    private array $selector = [];
    private string $status = Storage::STATUS_SUCCESS;
    public function __construct(public array $rows) {}
    public function config($key, $value): self { return $this; }
    public function activate(): self { return $this; }
    public function clear(): self { $this->selector = []; return $this; }
    public function where($key, $value): self { $this->selector[$key] = $value; return $this; }
    public function order($key, $direction): self { return $this; }
    public function read(): self
    {
        $this->status = $this->readFails ? Storage::STATUS_FAILED : Storage::STATUS_SUCCESS;
        return $this;
    }
    public function result(): object
    {
        $rows = array_values(array_filter($this->rows, fn ($row) => $this->matches($row)));
        return new class($rows) {
            public function __construct(private array $rows) {}
            public function toArray(): array { return $this->rows; }
        };
    }
    public function assign(array $selector): self { $this->selector = $selector; return $this; }
    public function delete(): self
    {
        $this->status = $this->deleteFails ? Storage::STATUS_FAILED : Storage::STATUS_SUCCESS;
        if (!$this->deleteFails) {
            $this->deleted[] = $this->selector;
            $this->rows = array_values(array_filter($this->rows, fn ($row) => !$this->matches($row)));
        }
        return $this;
    }
    public function status(): string { return $this->status; }
    private function matches(array $row): bool
    {
        foreach ($this->selector as $key => $value) {
            if (($row[$key] ?? null) !== $value) { return false; }
        }
        return true;
    }
}
