<?php

namespace BlueFission\BlueCore\Registration;

use BlueFission\Arr;
use BlueFission\BlueCore\Hooks\DispatchesLifecycleHooks;
use BlueFission\BlueCore\Hooks\LifecycleFailure;
use BlueFission\Collections\Collection;
use BlueFission\Str;
use BlueFission\Val;
use Throwable;
use UnexpectedValueException;

class RegistrationPlan
{
    use DispatchesLifecycleHooks;

    public const FILTER_ENTRY = 'bluecore.registration.plan.entry';
    public const HOOK_ENTRY_ADDED = 'bluecore.registration.plan.entry.added';
    public const HOOK_ENTRY_FAILED = 'bluecore.registration.plan.entry.failed';

    private array $services = [];
    private array $delegates = [];
    private array $bindings = [];
    private array $themes = [];
    private array $addons = [];

    public function service(string $name, mixed $definition): self
    {
        return $this->put('services', $name, $definition);
    }

    public function delegate(string $name, mixed $definition): self
    {
        return $this->put('delegates', $name, $definition);
    }

    public function binding(string $name, mixed $definition): self
    {
        return $this->put('bindings', $name, $definition);
    }

    public function theme(string $name, string $location = null): self
    {
        return $this->put('themes', $name, [
            'name' => $name,
            'location' => $location,
        ]);
    }

    public function addon(string $name, array $definition = []): self
    {
        return $this->put('addons', $name, Arr::make(['name' => $name])->merge($definition)->toArray());
    }

    public function entries(string $section): array
    {
        if (!Arr::hasKey($this->sections(), $section)) {
            throw new \InvalidArgumentException("Unknown registration section '{$section}'.");
        }

        return (new Collection($this->{$section}))->toArray();
    }

    public function toArray(): array
    {
        return Arr::make($this->sections())->toArray();
    }

    private function put(string $section, string $name, mixed $definition): self
    {
        try {
            $entry = $this->applyLifecycleFilter(
                self::FILTER_ENTRY,
                new RegistrationEntry($section, $name, $definition)
            );

            if (!$entry instanceof RegistrationEntry) {
                throw new UnexpectedValueException(
                    'Registration entry filters must return a RegistrationEntry.'
                );
            }

            if (Val::isEmpty($entry->name())) {
                throw new \InvalidArgumentException('Registration names cannot be empty.');
            }

            if (!Str::match($entry->section(), $section)) {
                throw new UnexpectedValueException(
                    'Registration entry filters cannot move entries between sections.'
                );
            }

            $this->{$entry->section()}[$entry->name()] = $entry->definition();
            $this->dispatchLifecycleAction(self::HOOK_ENTRY_ADDED, [$entry, $this]);
        } catch (Throwable $exception) {
            $this->dispatchLifecycleAction(self::HOOK_ENTRY_FAILED, [
                new LifecycleFailure(
                    self::HOOK_ENTRY_FAILED,
                    'compose',
                    self::class,
                    $exception
                ),
            ]);

            throw $exception;
        }

        return $this;
    }

    private function sections(): array
    {
        return [
            'services' => $this->services,
            'delegates' => $this->delegates,
            'bindings' => $this->bindings,
            'themes' => $this->themes,
            'addons' => $this->addons,
        ];
    }
}
