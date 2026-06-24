# SQLite Model Boundary

BlueCore uses `BlueFission\BlueCore\Model\ModelSQLite` as a model-level projection over local SQLite storage.

The upstream DevElation storage adapter is `BlueFission\Data\Storage\SQLite`. That adapter remains the canonical storage contract for direct SQLite data access, status vocabulary, query diagnostics, and broad storage behavior. BlueCore should rely on that contract when it needs generic SQLite storage.

`SQLiteModelStore` remains in BlueCore for the model-specific layer that is not a direct replacement for the upstream adapter:

- explicit model field projection from `ModelSQLite::$_fields`
- optional `column_types` for model-local schema shape
- materialized `Group` results for model query reads
- single-table model query diagnostics
- predictable `created` and `updated` timestamp behavior from `ModelSQLite`

The boundary is intentionally narrow. New storage primitives should go upstream to DevElation first. BlueCore should keep only the model-facing behavior that binds storage to the framework model contract.

## Diagnostics

`SQLiteModelStore::diagnostics()` returns a compact trace shape:

```php
[
    'upstreamStorage' => BlueFission\Data\Storage\SQLite::class,
    'table' => 'records',
    'key' => 'record_id',
    'query' => 'SELECT * FROM `records`',
    'status' => BlueFission\Data\Storage\Storage::STATUS_SUCCESS,
    'rowCount' => 1,
]
```

Use the diagnostics for tests, trace output, and operational inspection. Do not parse SQL strings for application decisions.
