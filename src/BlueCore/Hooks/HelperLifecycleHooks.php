<?php

namespace BlueFission\BlueCore\Hooks;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;
use Throwable;

final class HelperLifecycleHooks
{
    public const FILTER_RESPONSE = 'bluecore.response.prepare';
    public const HOOK_RESPONSE_BEFORE = 'bluecore.response.before';
    public const HOOK_RESPONSE_AFTER = 'bluecore.response.after';
    public const HOOK_RESPONSE_FAILED = 'bluecore.response.failed';
    public const EVENT_RESPONSE_PREPARED = 'bluecore.response.prepared';

    public const FILTER_TEMPLATE_DATA = 'bluecore.template.data';
    public const FILTER_TEMPLATE_OUTPUT = 'bluecore.template.output';
    public const HOOK_TEMPLATE_BEFORE = 'bluecore.template.before';
    public const HOOK_TEMPLATE_AFTER = 'bluecore.template.after';
    public const HOOK_TEMPLATE_FAILED = 'bluecore.template.failed';

    public const HOOK_ERROR_REPORTED = 'bluecore.error.reported';
    public const HOOK_EXCEPTION_REPORTED = 'bluecore.exception.reported';
    public const HOOK_FATAL_REPORTED = 'bluecore.fatal.reported';

    private static array $activeHooks = [];

    public static function apply(string $hook, mixed $value): mixed
    {
        if (!self::enter($hook)) {
            return $value;
        }

        try {
            return Dev::apply($hook, $value);
        } finally {
            self::leave($hook);
        }
    }

    public static function action(string $hook, array $arguments = []): void
    {
        if (!self::enter($hook)) {
            return;
        }

        try {
            Dev::do($hook, $arguments);
        } finally {
            self::leave($hook);
        }
    }

    public static function event(string $event, array $arguments = []): void
    {
        if (!self::enter($event)) {
            return;
        }

        try {
            Dev::trigger($event, $arguments);
        } finally {
            self::leave($event);
        }
    }

    public static function failure(string $hook, LifecycleFailure $failure): void
    {
        try {
            self::action($hook, [$failure]);
        } catch (Throwable) {
            error_log("BlueCore failure hook '{$hook}' raised an exception.");
        }
    }

    private static function enter(string $hook): bool
    {
        if (Arr::hasKey(self::$activeHooks, $hook)) {
            return false;
        }

        self::$activeHooks[$hook] = true;

        return true;
    }

    private static function leave(string $hook): void
    {
        unset(self::$activeHooks[$hook]);
    }
}
