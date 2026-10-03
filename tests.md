# Testing BlueCore

## Baseline

The default suite is provider-free and should pass on a clean PHP 8.2+ checkout after Composer dependencies are installed.

```bash
composer lint
vendor/bin/phpunit --do-not-cache-result
composer test:installer
```

Run a focused area with a repository-relative path:

```bash
vendor/bin/phpunit --do-not-cache-result tests/BlueCore/Installation
vendor/bin/phpunit --do-not-cache-result tests/Helpers/TemplateRenderingHelperTest.php
```

`composer lint` parses source, tests, and examples. `composer test:installer` creates isolated temporary Composer projects and verifies install, update, repeat-install, and uninstall parity. It may require network access and Composer cache access even though it does not require an application service stack.

## Optional integrations

Optional tests must skip cleanly when their requirements are absent.

| Capability | Requirement | Configuration |
| --- | --- | --- |
| SQLite model tests | PHP SQLite3 extension | No service credentials |
| MySQL datasource tests | Compatible MySQL service and DevElation connection support | Use the `DEV_ELATION_MYSQL_*` environment variables expected by the upstream test contract |
| MongoDB integration | PHP MongoDB extension and reachable MongoDB service | Use upstream documented environment variables; do not add local credentials to the repository |
| Memcached integration | PHP Memcached extension and reachable service | Use upstream documented environment variables; keep disabled by default |
| Presence bridge | Optional Presence package contracts | Provider-free fixtures are preferred; do not require a live identity service for the baseline suite |
| Network-backed generation | Host-supplied provider and credentials | Not part of the baseline suite; use fakes for BlueCore contract tests |

When service-backed behavior is not under test, do not enable extra services or edit `.env` merely to make the baseline suite pass.

## Test design

- Mirror source areas under `tests/`.
- Prefer deterministic provider-free fixtures.
- Assert structured outcomes, next actions, retained recovery evidence, and bounded hook payloads.
- Use subprocess fixtures for global error, shutdown, Composer, or process-lifecycle behavior.
- Preserve successful legacy callers when adding return receipts or diagnostics.
- Add regression coverage before changing shared lifecycle, registration, installer, migration, or helper contracts.

## Reporting results

Pull requests should list exact commands, counts, skipped optional tests, environment requirements, and any limitation that prevents full verification. Never include credentials or machine-specific paths.
