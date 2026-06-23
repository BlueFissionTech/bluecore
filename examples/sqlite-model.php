<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use BlueFission\BlueCore\Model\ModelSQLite;
use BlueFission\Utils\Path;

final class ExampleTaskModel extends ModelSQLite
{
    protected $_table = 'example_tasks';
    protected $_fields = [
        'task_id',
        'title',
        'status',
    ];
}

$runtime = bluecore_example_runtime_path('sqlite-model');
$database = $runtime . DIRECTORY_SEPARATOR . 'tasks.sqlite';

if (file_exists($database)) {
    unlink($database);
}

$tasks = new ExampleTaskModel($database);
$tasks->write([
    'title' => 'Review package examples',
    'status' => 'active',
]);

$tasks->clear();
$tasks->write([
    'title' => 'Confirm CI coverage',
    'status' => 'queued',
]);

$reader = new ExampleTaskModel($database);
$reader->read();

$rows = array_map(static function (array $row): array {
    return [
        'task_id' => (int)$row['task_id'],
        'title' => $row['title'],
        'status' => $row['status'],
    ];
}, $reader->result()->toArray());

bluecore_example_json([
    'database' => Path::normalize($database),
    'table' => 'example_tasks',
    'rows' => $rows,
]);
