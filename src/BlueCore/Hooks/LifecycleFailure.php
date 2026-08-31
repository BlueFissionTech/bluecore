<?php

namespace BlueFission\BlueCore\Hooks;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Str;
use Throwable;

final class LifecycleFailure extends Obj
{
    public function __construct(
        string $hook,
        string $operation,
        string $dispatcher,
        Throwable $exception
    ) {
        parent::__construct();

        $this->_data = Arr::make([
            'hook' => Str::make($hook)->trim()->val(),
            'operation' => Str::make($operation)->trim()->val(),
            'dispatcher' => Str::make($dispatcher)->trim()->val(),
            'exception_type' => $exception::class,
            'exception_code' => Num::make($exception->getCode())->val(),
        ]);
    }

    public function hook(): string
    {
        return $this->_data['hook'];
    }

    public function operation(): string
    {
        return $this->_data['operation'];
    }

    public function dispatcher(): string
    {
        return $this->_data['dispatcher'];
    }

    public function exceptionType(): string
    {
        return $this->_data['exception_type'];
    }

    public function exceptionCode(): int|float
    {
        return $this->_data['exception_code'];
    }
}
