<?php

use BlueFission\Arr;
use BlueFission\BlueCore\Hooks\HelperLifecycleHooks;
use BlueFission\DevElation;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

DevElation::up();

DevElation::filter(
    HelperLifecycleHooks::FILTER_RESPONSE,
    function (Arr $context): Arr {
        $data = Arr::make($context['data']);
        $data->set('meta', ['framework' => 'bluecore']);
        $context->set('data', $data->val());

        return $context;
    }
);

DevElation::action(
    HelperLifecycleHooks::HOOK_RESPONSE_BEFORE,
    function (Arr $summary): void {
        store('last_response_status', $summary['status']);
    }
);

DevElation::listen(HelperLifecycleHooks::EVENT_RESPONSE_PREPARED);
DevElation::subscribe(
    function (Arr $summary): void {
        store('response_prepared', $summary->val());
    },
    HelperLifecycleHooks::EVENT_RESPONSE_PREPARED
);

response([
    'status' => 'ready',
]);
