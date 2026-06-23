<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use BlueFission\Utils\Path;

function bluecore_example_runtime_path(string $name): string
{
    $base = Path::ensureDir(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-examples');

    return Path::ensureDir($base . DIRECTORY_SEPARATOR . $name);
}

function bluecore_example_json(array $payload): void
{
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
