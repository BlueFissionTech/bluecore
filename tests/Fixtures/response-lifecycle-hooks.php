<?php

use BlueFission\Arr;
use BlueFission\BlueCore\Hooks\HelperLifecycleHooks;
use BlueFission\BlueCore\Hooks\LifecycleFailure;
use BlueFission\DevElation as Dev;

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$mode = $argv[1] ?? 'success';

if ($mode === 'inactive') {
    Dev::filter(HelperLifecycleHooks::FILTER_RESPONSE, function (Arr $context): Arr {
        echo 'filter|';

        return $context;
    });
    response(['status' => 'inactive']);
}

Dev::up();

if ($mode === 'invalid') {
    Dev::filter(HelperLifecycleHooks::FILTER_RESPONSE, fn(Arr $context): array => $context->val());
    Dev::action(
        HelperLifecycleHooks::HOOK_RESPONSE_FAILED,
        function (LifecycleFailure $failure): void {
            echo 'failed:' . $failure->exceptionType() . '|';
        }
    );

    try {
        response(['status' => 'invalid']);
    } catch (UnexpectedValueException) {
        echo 'caught';
    }

    exit(0);
}

Dev::listen(HelperLifecycleHooks::EVENT_RESPONSE_PREPARED);
Dev::subscribe(function (Arr $summary): void {
    echo 'prepared:' . $summary['status'] . '|';
}, HelperLifecycleHooks::EVENT_RESPONSE_PREPARED);
Dev::filter(HelperLifecycleHooks::FILTER_RESPONSE, function (Arr $context): Arr {
    echo 'filter|';
    $context->set('data', ['status' => 'filtered']);
    $context->set('status', 202);

    return $context;
});
Dev::action(HelperLifecycleHooks::HOOK_RESPONSE_BEFORE, function (Arr $context): void {
    echo 'before:' . $context['status'] . '|';
});
Dev::action(HelperLifecycleHooks::HOOK_RESPONSE_AFTER, function (Arr $summary): void {
    echo 'after:' . $summary['status'] . '|';
});

response(['status' => 'original']);
