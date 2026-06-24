<?php

namespace BlueFission\BlueCore\Integration\Vibe;

use BlueFission\Arr;
use BlueFission\BlueCore\Gateway\AuthenticationGateway;
use BlueFission\BlueCore\Gateway\CacheGateway;
use BlueFission\BlueCore\Gateway\CorsGateway;
use BlueFission\BlueCore\Gateway\CsrfGateway;
use BlueFission\BlueCore\Gateway\DynamicGateway;
use BlueFission\BlueCore\Model\ModelSQLite;
use BlueFission\BlueCore\Model\ModelSql;
use BlueFission\Str;
use BlueFission\Val;

class DeclarativeIntegrationMapper
{
    private const GATEWAY_MAP = [
        'auth' => AuthenticationGateway::class,
        'authentication' => AuthenticationGateway::class,
        'cache' => CacheGateway::class,
        'cors' => CorsGateway::class,
        'csrf' => CsrfGateway::class,
        'dynamic' => DynamicGateway::class,
    ];

    public function map(array|object $declaration): array
    {
        $spec = Arr::toArray((array)$declaration, true);

        return Arr::make([
            'models' => $this->models($spec),
            'gateways' => $this->gateways($spec),
            'app' => $this->app($spec),
        ])->toArray();
    }

    private function models(array $spec): array
    {
        $data = $this->section($spec, ['data', '#data']);
        $models = [];

        foreach ($data as $key => $entry) {
            $entry = Arr::toArray((array)$entry, true);
            $name = $this->entryName($key, $entry);
            if (Val::isEmpty($name)) {
                continue;
            }

            $backend = Str::lower((string)(Arr::getPath($entry, 'backend', 'sqlite')));
            $keyField = Arr::getPath($entry, 'key', Str::snake($name) . '_id');
            $fields = Arr::toArray(Arr::getPath($entry, 'fields', []), true);
            if (!Arr::has($fields, $keyField, true)) {
                Arr::unshift($fields, $keyField);
            }

            $models[] = [
                'name' => $name,
                'backend' => $backend,
                'class' => $backend === 'sql' || $backend === 'mysql' ? ModelSql::class : ModelSQLite::class,
                'table' => Arr::getPath($entry, 'table', Str::pluralize(Str::snake($name))),
                'key' => $keyField,
                'fields' => $fields,
            ];
        }

        return $models;
    }

    private function gateways(array $spec): array
    {
        $mods = $this->section($spec, ['mods', '@mod', 'gateways']);
        $gateways = [];

        foreach ($mods as $key => $entry) {
            $entry = Arr::toArray((array)$entry, true);
            $name = $this->entryName($key, $entry);
            if (Val::isEmpty($name)) {
                continue;
            }

            $type = Str::lower((string)Arr::getPath($entry, 'type', $name));
            $gateways[] = [
                'name' => $name,
                'type' => $type,
                'class' => self::GATEWAY_MAP[$type] ?? DynamicGateway::class,
                'options' => Arr::toArray(Arr::getPath($entry, 'options', []), true),
            ];
        }

        return $gateways;
    }

    private function app(array $spec): array
    {
        $app = Arr::toArray(Arr::getPath($spec, 'app', []), true);

        return [
            'services' => Arr::toArray(Arr::getPath($app, 'services', []), true),
            'bindings' => Arr::toArray(Arr::getPath($app, 'bindings', []), true),
            'delegates' => Arr::toArray(Arr::getPath($app, 'delegates', []), true),
        ];
    }

    private function section(array $spec, array $keys): array
    {
        foreach ($keys as $key) {
            $section = Arr::getPath($spec, $key, null);
            if (Val::isNotNull($section)) {
                return Arr::toArray((array)$section, true);
            }
        }

        return [];
    }

    private function entryName(string|int $key, array $entry): string
    {
        $name = Arr::getPath($entry, 'name', Str::is($key) ? $key : '');

        return Str::trim((string)$name);
    }
}
