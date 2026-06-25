<?php

$roots = ['src', 'tests', 'examples'];
$failures = [];

foreach ($roots as $root) {
    if (!is_dir($root)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
        exec($command, $output, $exitCode);
        foreach ($output as $line) {
            echo $line . PHP_EOL;
        }
        if ($exitCode !== 0) {
            $failures[] = $path;
        }
        $output = [];
    }
}

if ($failures) {
    fwrite(STDERR, PHP_EOL . 'PHP lint failed for:' . PHP_EOL);
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo 'PHP lint passed.' . PHP_EOL;
