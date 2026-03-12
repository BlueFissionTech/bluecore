<?php

namespace BlueFission\Tests\BlueCore\Model;

use BlueFission\BlueCore\Model\ModelSQLite;
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
}

class TestSQLiteModel extends ModelSQLite
{
    protected $_table = 'test_records';
    protected $_fields = [
        'record_id',
        'name',
        'status',
    ];
}
