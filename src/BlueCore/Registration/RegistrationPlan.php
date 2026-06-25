<?php

namespace BlueFission\BlueCore\Registration;

use BlueFission\Arr;
use BlueFission\Collections\Collection;
use BlueFission\Val;

class RegistrationPlan
{
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
        if (Val::isEmpty($name)) {
            throw new \InvalidArgumentException('Registration names cannot be empty.');
        }

        $this->{$section}[$name] = $definition;

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
