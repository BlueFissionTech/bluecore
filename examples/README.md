# BlueCore Examples

These examples are small, runnable scripts that demonstrate the current package APIs without optional services or application boot state. They are intended to show package-owned patterns for contributors and integrators while keeping runtime side effects isolated to the system temporary directory.

Run an example from the package root after installing dependencies:

```bash
php examples/file-path-utilities.php
php examples/sqlite-model.php
php examples/generator-factory.php
php examples/menu-composition.php
```

The normal CI lint command also parses the examples:

```bash
composer lint
```

## Coverage

- `file-path-utilities.php` shows path normalization, directory creation, safe file creation, and atomic writes through the BlueCore utility facades backed by DevElation primitives.
- `sqlite-model.php` shows a minimal `ModelSQLite` aggregate with deterministic temporary storage.
- `generator-factory.php` shows how to inject generation dependencies and request concrete generator types without requiring network-backed AI services.
- `menu-composition.php` shows menu and nested menu-item composition without requiring theme rendering.
