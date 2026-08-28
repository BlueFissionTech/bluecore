<?php

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use BlueFission\BlueCore\Integration\Presence\PresenceBridgeFactory;
use BlueFission\BlueCore\Integration\Presence\PresenceBridgeUnavailable;

try {
    PresenceBridgeFactory::make();
    echo json_encode(['available' => true], JSON_THROW_ON_ERROR);
} catch (PresenceBridgeUnavailable $exception) {
    echo json_encode([
        'available' => false,
        'reason' => $exception->reason(),
        'details' => $exception->details(),
    ], JSON_THROW_ON_ERROR);
}
