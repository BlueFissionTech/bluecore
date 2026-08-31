<?php
namespace BlueFission\BlueCore\Gateway;

use BlueFission\Arr;
use BlueFission\Services\Gateway;
use BlueFission\Services\Request;
use BlueFission\DevElation as Dev;
use UnexpectedValueException;

class DynamicGateway extends Gateway {

    public const FILTER_ARGUMENTS = 'bluecore.gateway.dynamic.arguments';
    public const LEGACY_FILTER_ARGUMENTS = 'opus_gateway.dynamic.process';

    public function __construct() {}

    public function process(Request $request, &$arguments): void {
        $arguments = $this->applyArgumentsFilter(self::LEGACY_FILTER_ARGUMENTS, $arguments);
        $arguments = $this->applyArgumentsFilter(self::FILTER_ARGUMENTS, $arguments);
    }

    private function applyArgumentsFilter(string $name, mixed $arguments): array
    {
        $filtered = Dev::apply($name, $arguments);

        if ($filtered instanceof Arr) {
            return $filtered->toArray(true);
        }

        if (!Arr::is($filtered)) {
            throw new UnexpectedValueException("Dynamic gateway filter '{$name}' must return an array or Arr.");
        }

        return Arr::toArray($filtered, true);
    }
}
