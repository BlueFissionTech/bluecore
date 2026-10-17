# SQLite shared-context acceptance boundary

This is the proposed acceptance contract for issue #110, not an available
consumer API or a completed implementation. The single-write changes in #111
do not provide a shared transaction. Do not adopt a guessed constructor,
transaction helper, or unreleased dependency surface from this document.

## Characterized behavior

`SQLiteContextBoundaryTest` uses real models, disposable SQLite databases and
synthetic triggers. It does not invoke a production host command or certify
that host's recovery behavior.

| Probe | Observed legacy/single-write boundary | Shared-context acceptance requirement |
| --- | --- | --- |
| Ignored or aborted history insertion after a model write | History throws, but the model remains changed after reopen | Both model and history stay unchanged after verified rollback |
| Same-value retry after that failure | A representative state-equality short circuit leaves history absent | A stable operation identity and reviewed retry protocol distinguish incomplete work from a completed no-op |
| Relation insertion fails inside a separate PDO transaction | PDO rollback does not undo an independently committed model write | Relations, models and history use the identical execution context |
| An `AFTER UPDATE` trigger rewrites a field | Accepted affected-row count and model fields differ from fresh stored values | Fresh-value verification is explicit and separate from affected-write validation |

These tests **pass by exposing the unsupported boundary**. They are not passing
atomicity, recovery, concurrency or read-only acceptance tests. A model/history
operation must not be declared atomic merely because both operations use the
same database filename or both use SQLite.

## Ownership and current prerequisite

The inspected installed DevElation version is v1.3.44 at
`096a10250164227662134f996f6ddd083605a5c7`. Its inherited
`Connection::connection()` exposes the native handle, but that alone is not a
supported shared storage context. `SQLite::activate()` constructs a new link;
`SQLiteLink::query()` catches failures into status, and the installed opening
path has no noncreating/read-only mode. Connection pooling does not establish
transaction ownership or strict outcomes.

DevElation owns reusable connection and storage execution. BlueCore owns model
injection, projection, timestamps and compatibility. The selected direction is
a DevElation-owned strict execution and noncreating read-only context, followed
by the BlueCore adapter, not an interim parallel transaction engine. Before an
adapter is implemented or adopted, the exact public contract and release/ref
must be agreed with that owner. No dependency upgrade is authorized by this
proposal.

## Required callable consumer contract

The eventual reviewed API must let a host:

1. Open one explicitly owned writable context, or one noncreating read-only
   context. Missing-file read-only access must create nothing. Reads must not
   install schema; schema setup is a separate authorized operation.
2. Bind multiple model instances and relation operations to that exact context
   without reactivation replacing its connection. Cross-database binding fails
   closed. No fallback to an independently opened connection is allowed.
3. Enter a unit of work with explicit nested ownership policy. A borrowed
   participant must not commit, roll back or close its caller's context.
4. Check prepare, bind, execute and affected-row outcomes before dependent work.
   Publish committed success only after commit is verified; model fields and
   generated identifiers before commit are not durable evidence.
5. Treat commit or rollback uncertainty as incomplete/unknown, retain the
   original error and prohibit blind retry. Release only owned resources.
6. Request fresh persisted-value readback where exact field equality matters.
   Affected-row validation alone does not verify trigger-rewritten values.
7. Provide a host-owned stable operation identity and uniqueness/retry policy
   for concurrent duplicate requests. Storage transactions alone must not be
   advertised as exactly-once application semantics.

No method names or signatures are promised until the owning contract is
approved. The consumer must continue to withhold acceptance when this callable
surface is unavailable; #111 is not a substitute.

## Implementation gate and validation

Before the shared-context slice can be accepted, test ignored/aborted model and
history writes, relation rollback, reopen, same-value retries, successful commit,
failed/uncertain commit and rollback, nested ownership, cleanup, missing-file
read-only behavior, cross-file isolation and concurrent duplicates. Use two
distinct models plus history and relations. Record which cases have actually
executed and keep unsupported cases explicit.

Run the current boundary characterizations with:

```sh
php vendor/bin/phpunit --do-not-cache-result tests/BlueCore/Model/SQLiteContextBoundaryTest.php
php vendor/bin/phpunit --do-not-cache-result tests/BlueCore/Model/ModelSQLiteTest.php
```

The optional PDO SQLite probe skips when that driver is absent. No service or
dependency installation is needed. Legacy defaults and model public return
shapes must remain compatible unless a separately reviewed migration says
otherwise. No live schema change, release, activation or merge is authorized.
