<?php

use BlueFission\Arr;
use BlueFission\BlueCore\Hooks\HelperLifecycleHooks;
use BlueFission\DevElation as Dev;
use BlueFission\Net\HTTP;
use BlueFission\Utils\File;

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$mode = $argv[1] ?? 'suppressed-warning';
$evidencePath = $argv[2] ?? '';

Dev::up();
Dev::action(
    HelperLifecycleHooks::HOOK_FATAL_REPORTED,
    function (Arr $summary) use ($evidencePath): void {
        if ($evidencePath === '') {
            return;
        }

        File::ensureFile($evidencePath, HTTP::jsonEncode($summary->toArray()), true);
    }
);

if ($mode === 'suppressed-warning') {
    // Exercise PHP's suppression and error_get_last() behavior at the native boundary.
    @file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'missing-shutdown-warning.txt');
    echo 'completed';
    exit(0);
}

if ($mode === 'handled-warning') {
    trigger_error('handled warning', E_USER_WARNING);
    echo 'completed';
    exit(0);
}

restore_exception_handler();
blueCoreMissingFunctionForShutdownTest();
