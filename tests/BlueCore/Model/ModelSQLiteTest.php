<?php

namespace BlueFission\Tests\BlueCore\Model;

use BlueFission\BlueCore\Model\ModelSQLite;
use BlueFission\BlueCore\Model\SQLiteModelStore;
use BlueFission\Data\Storage\SQLite as DevElationSQLite;
use BlueFission\Data\Storage\Storage;
use PHPUnit\Framework\TestCase;

class ModelSQLiteTest extends TestCase
{
    private $database;

    protected function setUp(): void
    {
        $this->database = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-model-' . uniqid('', true) . '.sqlite';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->database)) {
            unlink($this->database);
        }
    }

    public function testWriteAndReadUsingSQLiteModelBase(): void
    {
        $model = new TestSQLiteModel($this->database);
        $model->write(['name' => 'alpha', 'status' => 'active']);

        $this->assertGreaterThan(0, $model->id());
        $this->assertStringContainsString('INSERT', strtoupper($model->query()));

        $reader = new TestSQLiteModel($this->database);
        $reader->field('name', 'alpha');
        $reader->read();

        $this->assertSame('alpha', $reader->field('name'));
        $this->assertSame('active', $reader->field('status'));
        $this->assertNotEmpty($reader->field('created'));
        $this->assertNotEmpty($reader->field('updated'));
    }

    public function testResultReturnsMaterializedRows(): void
    {
        $writer = new TestSQLiteModel($this->database);
        $writer->write(['name' => 'first', 'status' => 'active']);
        $writer->clear();
        $writer->write(['name' => 'second', 'status' => 'draft']);

        $reader = new TestSQLiteModel($this->database);
        $reader->read();
        $rows = $reader->result()->toArray();

        $this->assertCount(2, $rows);
        $this->assertSame('first', $rows[0]['name']);
        $this->assertSame('second', $rows[1]['name']);
    }

    public function testFalseyScalarAssignmentsArePersisted(): void
    {
        $writer = new TestSQLiteModel($this->database);
        $writer->write(['name' => 'switch', 'status' => 'active', 'is_active' => 1]);
        $recordId = $writer->id();

        $writer->write([
            'record_id' => $recordId,
            'status' => '',
            'is_active' => 0,
        ]);

        $reader = new TestSQLiteModel($this->database);
        $reader->read(['record_id' => $recordId]);

        $this->assertSame('', $reader->field('status'));
        $this->assertSame(0, (int)$reader->field('is_active'));
    }

    public function testSQLiteStoreBoundaryNamesDevElationStorageAndLocalResponsibilities(): void
    {
        $boundary = SQLiteModelStore::boundary();

        $this->assertSame(DevElationSQLite::class, $boundary['upstreamStorage']);
        $this->assertSame('model_projection_store', $boundary['blueCoreRole']);
        $this->assertContains('sqlite_storage_contract', $boundary['delegatesToUpstreamFor']);
        $this->assertContains('materialized_group_results', $boundary['retainsLocally']);
    }

    public function testSQLiteStoreDiagnosticsExposeQueryStatusAndRows(): void
    {
        $writer = new TestSQLiteModel($this->database);
        $writer->write(['name' => 'first', 'status' => 'active']);

        $reader = new TestSQLiteModel($this->database);
        $reader->read();

        $diagnostics = $reader->storeDiagnostics();

        $this->assertSame(DevElationSQLite::class, $diagnostics['upstreamStorage']);
        $this->assertSame('test_records', $diagnostics['table']);
        $this->assertSame('record_id', $diagnostics['key']);
        $this->assertStringContainsString('SELECT', $diagnostics['query']);
        $this->assertSame(Storage::STATUS_SUCCESS, $diagnostics['status']);
        $this->assertSame(1, $diagnostics['rowCount']);
    }

    public function testIgnoredInsertFailsWithoutPublishingIdAndSameModelCanRetry(): void
    {
        $seed = new TestSQLiteModel($this->database);
        $seed->write(['name' => 'seed']);
        $db = new \SQLite3($this->database);
        $db->exec("CREATE TRIGGER deny_insert BEFORE INSERT ON test_records BEGIN SELECT RAISE(IGNORE); END");
        $writer = new TestSQLiteModel($this->database);
        try {
            $writer->write(['name' => 'retry']);
            $this->fail('Ignored insert must throw.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('exactly one row', $error->getMessage());
        }
        $this->assertSame(Storage::STATUS_FAILED, $writer->status());
        $this->assertNull($writer->id());
        $this->assertSame(1, $db->querySingle('SELECT count(*) FROM test_records'));
        $db->exec('DROP TRIGGER deny_insert');
        $this->assertSame($writer, $writer->write(['name' => 'retry']));
        $this->assertSame(Storage::STATUS_SUCCESS, $writer->status());
        $this->assertSame(2, $db->querySingle('SELECT count(*) FROM test_records'));
        $db->close();
    }

    public function testIgnoredUpdateRollsBackTriggerHistoryAndAcceptedNoOpCanRetry(): void
    {
        $writer = new TestSQLiteModel($this->database);
        $writer->write(['name' => 'record', 'status' => 'draft']);
        $id = $writer->id();
        $db = new \SQLite3($this->database);
        $db->exec('CREATE TABLE history (id INTEGER PRIMARY KEY, state TEXT)');
        $db->exec("CREATE TRIGGER deny_update BEFORE UPDATE ON test_records BEGIN INSERT INTO history(state) VALUES ('active'); SELECT RAISE(IGNORE); END");
        try {
            $writer->write(['record_id' => $id, 'name' => 'record', 'status' => 'active']);
            $this->fail('Ignored update must throw.');
        } catch (\RuntimeException) {
            $this->assertSame(Storage::STATUS_FAILED, $writer->status());
        }
        $this->assertSame('draft', $db->querySingle('SELECT status FROM test_records'));
        $this->assertSame(0, $db->querySingle('SELECT count(*) FROM history'));
        $db->exec('DROP TRIGGER deny_update');
        $writer->write(['record_id' => $id, 'name' => 'record', 'status' => 'draft']);
        $this->assertSame(Storage::STATUS_SUCCESS, $writer->status());
        $this->assertSame(1, $db->querySingle('SELECT count(*) FROM test_records'));
        $db->close();
    }

    public function testAbortedWriteRollsBackHistorySideEffectsAndReopenRetryWorks(): void
    {
        $writer = new TestSQLiteModel($this->database);
        $writer->write(['name' => 'record', 'status' => 'draft']);
        $id = $writer->id();
        $db = new \SQLite3($this->database);
        $db->exec('CREATE TABLE history (id INTEGER PRIMARY KEY, state TEXT)');
        $db->exec("CREATE TRIGGER deny_update BEFORE UPDATE ON test_records BEGIN INSERT INTO history(state) VALUES ('active'); SELECT RAISE(FAIL, 'blocked'); END");
        try {
            $writer->write(['record_id' => $id, 'status' => 'active']);
            $this->fail('Aborted update must throw.');
        } catch (\Exception $error) {
            $this->assertStringContainsString('blocked', $error->getMessage());
        }
        $this->assertSame(Storage::STATUS_FAILED, $writer->status());
        $this->assertSame('draft', $db->querySingle('SELECT status FROM test_records'));
        $this->assertSame(0, $db->querySingle('SELECT count(*) FROM history'));
        $db->exec('DROP TRIGGER deny_update');
        $reopened = new TestSQLiteModel($this->database);
        $reopened->write(['record_id' => $id, 'name' => 'record', 'status' => 'active']);
        $this->assertSame('active', $db->querySingle('SELECT status FROM test_records'));
        $db->close();
    }

    public function testSeparateHistoryFailureDoesNotPretendToRollBackCommittedModel(): void
    {
        $writer = new TestSQLiteModel($this->database);
        $writer->write(['name' => 'record', 'status' => 'active']);
        $db = new \SQLite3($this->database);
        $db->exec('CREATE TABLE history (id INTEGER PRIMARY KEY, state TEXT)');
        $db->exec("CREATE TRIGGER deny_history BEFORE INSERT ON history BEGIN SELECT RAISE(IGNORE); END");
        $history = new SQLiteModelStore(['location' => $this->database, 'name' => 'history', 'fields' => ['id', 'state']]);
        $history->field('state', 'active');
        try {
            $history->write();
            $this->fail('Ignored history must throw.');
        } catch (\RuntimeException) {
            $this->assertSame(Storage::STATUS_FAILED, $history->status());
        }
        $this->assertSame('active', $db->querySingle('SELECT status FROM test_records'));
        $this->assertSame(0, $db->querySingle('SELECT count(*) FROM history'));
        $db->exec('DROP TRIGGER deny_history');
        $history->write();
        $this->assertSame(1, $db->querySingle('SELECT count(*) FROM history'));
        $db->close();
    }

    public function testCommitAndRollbackFailuresRetainFailedStatusAndOriginalCause(): void
    {
        $config = ['location' => $this->database, 'name' => 'records', 'fields' => ['id', 'value']];
        $store = new class($config) extends SQLiteModelStore {
            protected function open(): \SQLite3
            {
                return new class($this->config('location')) extends \SQLite3 {
                    public function exec(string $query): bool
                    {
                        if ($query === 'COMMIT' || $query === 'ROLLBACK') {
                            return false;
                        }
                        return parent::exec($query);
                    }
                };
            }
        };
        $store->field('value', 'pending');
        try {
            $store->write();
            $this->fail('Unverified commit/rollback must throw.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('rollback could not be verified', $error->getMessage());
            $this->assertSame('SQLite model write commit failed.', $error->getPrevious()?->getMessage());
        }
        $this->assertSame(Storage::STATUS_FAILED, $store->status());
        $this->assertNull($store->field('id'));
        $db = new \SQLite3($this->database);
        $this->assertSame(0, $db->querySingle('SELECT count(*) FROM records'));
        $db->close();
    }
}

class TestSQLiteModel extends ModelSQLite
{
    protected $_table = 'test_records';
    protected $_fields = [
        'record_id',
        'name',
        'status',
        'is_active',
    ];

    public function storeDiagnostics(): array
    {
        return $this->_dataObject->diagnostics();
    }
}
