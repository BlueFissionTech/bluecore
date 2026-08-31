<?php

namespace BlueFission\BlueCore\Hooks;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;
use BlueFission\Num;

trait DispatchesLifecycleHooks
{
    private array $_activeLifecycleHooks = [];

    protected function dispatchLifecycleAction(string $hook, array $arguments = []): void
    {
        if (!$this->enterLifecycleHook($hook)) {
            return;
        }

        try {
            Dev::do($hook, $arguments);
        } finally {
            $this->leaveLifecycleHook($hook);
        }
    }

    protected function applyLifecycleFilter(string $hook, mixed $value): mixed
    {
        if (!$this->enterLifecycleHook($hook)) {
            return $value;
        }

        try {
            return Dev::apply($hook, $value);
        } finally {
            $this->leaveLifecycleHook($hook);
        }
    }

    private function enterLifecycleHook(string $hook): bool
    {
        if (Arr::hasKey($this->_activeLifecycleHooks, $hook)) {
            return false;
        }

        $this->_activeLifecycleHooks[$hook] = 1;

        return true;
    }

    private function leaveLifecycleHook(string $hook): void
    {
        $remaining = (int)Num::make($this->_activeLifecycleHooks[$hook] ?? 1)
            ->decrement()
            ->val();

        if ($remaining <= 0) {
            unset($this->_activeLifecycleHooks[$hook]);
        } else {
            $this->_activeLifecycleHooks[$hook] = $remaining;
        }
    }
}
