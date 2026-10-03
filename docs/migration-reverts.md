# Migration revert outcomes

`DatasourceManager::revertMigrations()` reverts successful history entries in
the latest applied iteration. `revertBatch($batch)` reverts successful entries
across the named batch's iterations. Both use descending iteration and history
ID order, retain each record's own batch/iteration, and return an array. An
empty batch name is rejected instead of selecting all batches.

The receipt includes `operation`, `batch`, `iteration` (latest selected), `ok`,
`changed`, `stage`, `total`, `reverted`, `failed`, `unattempted`, `results`,
`reason`, `error`, `exception`, and `nextAction`. Each result carries its
name, batch, iteration, history ID, status, concrete exception type/message,
`reverted`, and `historyRemoved`.

- Absent history or an empty batch is a successful no-op (`no_history` or
  `empty_history`). Files on disk without successful history are never reverted.
- A missing directory with applicable history is `unavailable`; restore the
  migration directory before proceeding.
- Reverts stop at the first failure. That entry and all unattempted entries
  retain their history. Earlier successful reverts remain reported as a partial
  outcome. Missing classes, include errors, and other `Throwable` failures are
  recorded rather than deleting history or escaping as an unqualified exception.
- History is removed only after `revert()` returns successfully. Deletion is
  scoped to the record's name, batch, iteration, status, and ID when present.
- If history cleanup fails after a successful revert, the receipt requests
  `reconcile_migration_history`. Inspect and reconcile state before retrying;
  blindly replaying a revert could apply it twice.

Successful callers may continue to ignore the new return value. Callers must
check `ok` to detect failures now returned as receipts. `changed` means at least
one revert returned successfully; a throwing migration may already have made
partial changes that cannot be inferred by the manager. Revert actions remain
responsible for their own transaction/connection behavior, and this receipt
does not promise an atomic rollback across the whole operation.

`AddOnManager::uninstall()` checks the datasource receipt before deleting the
add-on registration. Failed or partial reverts retain that registration and
propagate the receipt's recovery action, including history reconciliation.
