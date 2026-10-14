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

## Write outcomes and recovery

Each model write owns a fresh SQLite3 handle and a single-write transaction.
It returns the existing fluent model/store shape only after exactly one row
was affected and commit succeeded. Ignored writes (including `RAISE(IGNORE)`),
SQLite execution errors and failed commits throw; status remains failed and
no generated insert identifier is published. Trigger side effects of a failed
write are rolled back. A rollback failure throws with the original write
exception retained as its previous exception; its outcome must be reconciled,
not blindly retried. Temporary model timestamp configuration is restored even
when a write throws. Pending model fields are not durable readback: reload the
record before reporting stored state. An accepted same-value update remains a
successful one-row write; trigger-rewritten values require explicit readback.

This is **not** an atomic transaction across models. A transaction on another
PDO or SQLite3 handle cannot cover these writes. Do not append history after a
primary write throws. If a later history write fails, the earlier model may
already be committed: report incomplete work, reconcile durable records and
resume only missing evidence using a host-owned stable operation identity.
Do not claim complete success or increment created counts from a fluent return
without a verified write, and do not claim exactly-once retry without durable
uniqueness/idempotency constraints. These are host recovery responsibilities,
not an application audit schema in BlueCore.

Shared caller-owned execution, read-only lifecycle and schema-context support
remain tracked in issue #110 and the upstream DevElation contract. The inspected
installed DevElation v1.3.44 does not expose `SQLite::borrowConnection()`.
No unreleased API, dependency upgrade, shared transaction or multi-model
rollback is implied by the single-write guard. Legacy implicit schema setup
remains outside the write transaction and is not noncreating read-only access.

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
