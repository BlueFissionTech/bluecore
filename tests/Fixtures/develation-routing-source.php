<?php

/**
 * Opt-in source qualification only. The installed Composer platform checks and
 * all other locked dependencies remain active; no vendor file is modified.
 */
$loader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
$source = getenv('BLUECORE_DEVELATION_ROUTING_SOURCE');
if ($source === false || $source === '') {
    throw new RuntimeException('Set BLUECORE_DEVELATION_ROUTING_SOURCE to the native routing candidate checkout.');
}

$files = [
    \BlueFission\Services\Mapping::class => '/src/Services/Mapping.php',
    \BlueFission\Utils\Loader::class => '/src/Utils/Loader.php',
];
foreach ($files as $class => $relativePath) {
    $file = realpath($source . $relativePath);
    if ($file === false || !is_file($file)) {
        throw new RuntimeException('Native routing qualification source is incomplete.');
    }
    if (class_exists($class, false)) {
        throw new RuntimeException('Native routing source must be selected before its classes are loaded.');
    }
    $files[$class] = $file;
}
$loader->addClassMap($files);
unset($loader, $source, $files, $class, $relativePath, $file);
