# SPEC: Path and File Helpers

## Purpose
Provide small, cross-platform helpers for creating directories and files safely in BlueCore.

## Scope
- `BlueFission\Utils\Path::ensureDir($path, $mode = 0775, $recursive = true)` creates a directory when missing and returns a normalized path.
- `BlueFission\Utils\File::ensureFile($path, $contents = '', $overwrite = false)` creates a file and its parent directories when needed.
- `BlueFission\Utils\File::writeAtomic($path, $contents)` writes via a temp file and renames into place.
- `BlueFission\BlueCore\Model\ModelSQLite` provides a first-party SQLite-backed BlueCore model base analogous to `ModelSql`.
- Tests for directory creation, file creation, overwrite behavior, and atomic writes.
- Tests for SQLite-backed model write/read and materialized result sets.

## User Stories
- As a CLI tool, I can ensure a cache directory exists without duplicating mkdir logic.
- As a generator, I can write a file once and avoid accidental overwrites unless I opt in.
- As a service, I can write files atomically to reduce partial-write risk.
- As an app using local agent memory or planning state, I can inherit from a BlueCore SQLite model base instead of wiring raw SQLite storage in each model.

## Acceptance Criteria
- `ensureDir` returns a normalized path and creates the directory if missing.
- `ensureDir` throws when the path exists as a file.
- `ensureFile` creates parent directories and writes contents when missing.
- `ensureFile` does not overwrite existing files unless `$overwrite` is true.
- `writeAtomic` results in the target file containing the requested contents.
- `ModelSQLite` can create a table, write a row, read it back, and expose a `query()` string.
- `ModelSQLite::result()` returns a materialized collection of rows rather than a raw `SQLite3Result`.

## Non-goals
- No changes to `.env`, Docker, or dependency installation.
