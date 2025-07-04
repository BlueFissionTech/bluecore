<?php
namespace BlueFission\BlueCore\Gateway;

use BlueFission\Services\Gateway;
use BlueFission\Services\Request;
use BlueFission\DevElation as Dev;

class DynamicGateway extends Gateway {

    public function __construct() {}

    public function process(Request $request, &$arguments) {
        $arguments = Dev::apply('opus_gateway.dynamic.process', $arguments);
    }
}
