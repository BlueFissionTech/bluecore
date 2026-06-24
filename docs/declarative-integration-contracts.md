# Declarative Integration Contracts

BlueCore can consume declarative integration intent and map it into framework-owned model, gateway, and app registration configuration.

The first package-level mapper is `BlueFission\BlueCore\Integration\Vibe\DeclarativeIntegrationMapper`. The mapper accepts array or object declarations and returns a neutral BlueCore configuration shape:

```php
[
    'models' => [
        [
            'name' => 'Article',
            'backend' => 'sqlite',
            'class' => BlueFission\BlueCore\Model\ModelSQLite::class,
            'table' => 'articles',
            'key' => 'article_id',
            'fields' => ['article_id', 'title', 'body'],
        ],
    ],
    'gateways' => [
        [
            'name' => 'auth',
            'type' => 'auth',
            'class' => BlueFission\BlueCore\Gateway\AuthenticationGateway::class,
            'options' => [],
        ],
    ],
    'app' => [
        'services' => [],
        'bindings' => [],
        'delegates' => [],
    ],
]
```

## Data Declarations

Data declarations may be keyed by model name:

```php
[
    'data' => [
        'Article' => [
            'backend' => 'sqlite',
            'fields' => ['title', 'body'],
        ],
    ],
]
```

SQLite is the default backend for local iteration. SQL/MySQL declarations map to `ModelSql`; SQLite declarations map to `ModelSQLite`.

## Gateway Declarations

Gateway declarations may use `mods`, `@mod`, or `gateways`. Known gateway types map to BlueCore gateway classes:

- `auth` / `authentication`
- `cache`
- `cors`
- `csrf`
- `dynamic`

Unknown gateway types fall back to `DynamicGateway` so the declaration remains inspectable.

## Ownership Boundary

BlueCore owns the mapping from declarative intent to model and gateway configuration. Runtime-specific parsing, file formats, and compilation pipelines should remain outside this package unless they become reusable BlueCore capabilities.
