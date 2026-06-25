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
}

class TestSQLiteModel extends ModelSQLite
{
    protected $_table = 'test_records';
    protected $_fields = [
        'record_id',
        'name',
        'status',
    ];

    public function storeDiagnostics(): array
    {
        return $this->_dataObject->diagnostics();
    }
}
