# BlueCore Architecture

## System context and ownership

BlueCore is a reusable framework package between DevElation and application hosts. DevElation supplies primitives, behaviors, services, connections, parsing/template facilities, and generic storage. BlueCore composes those capabilities into application lifecycle and extension contracts. Application hosts own product routes, concrete service implementations, credentials, tenancy, deployment, and user-facing policy.

```text
Composer / application host
          |
          v
BlueCore framework contracts
  |       |        |        |
Engine  Registration  Installation  Gateways/models/helpers
          |
          v
DevElation primitives, services, storage, parsing, and hooks
```

## Entrypoints

### Composer activation

[`PluginInstaller`](src/BlueCore/Installers/PluginInstaller.php) is the Composer plugin entrypoint declared in `composer.json`. It registers add-on, theme, and project installers on activation and removes them on deactivation.

### Application runtime

[`Engine`](src/BlueCore/Engine.php) is the application runtime entrypoint. It owns active concrete instances, application-scoped resolution, configuration loading, helper/mapping discovery, gateway denial state, and bootstrap/process/run lifecycle hooks.

### Autoloaded helpers

Composer loads [`src/Helpers/global.php`](src/Helpers/global.php), [`src/Helpers/response.php`](src/Helpers/response.php), and [`src/Helpers/errors.php`](src/Helpers/errors.php). These expose convenience functions and register production error handling. Helper hook payloads are sanitized and bounded by [`HelperLifecycleHooks`](src/BlueCore/Hooks/HelperLifecycleHooks.php).

## Core domain components

### Registration

[`RegistrationPlan`](src/BlueCore/Registration/RegistrationPlan.php) stores services, delegates, bindings, themes, and add-ons. Filters can transform validated entries but cannot move them between sections. `Engine` resolves root contracts through application-owned bindings and retains lifecycle-phase diagnostics.

### Installation

[`InstallationExecutor`](src/BlueCore/Installation/InstallationExecutor.php) runs host-defined stages. Validation, approval gates, stage metadata, and optional checkpoint persistence are composed by the host. [`InstallationPlan`](src/BlueCore/Installation/InstallationPlan.php) retains status, diagnostics, outcomes, and recovery state.

### Add-ons and datasources

[`AddOnManager`](src/BlueCore/Business/Managers/AddOnManager.php) manages add-on installation, activation, registration factories, passive contributions, and datasource coordination. [`DatasourceManager`](src/BlueCore/Business/Managers/DatasourceManager.php) discovers migration/population work, validates plan filters, and records structured outcomes and migration history.

### Menus, themes, values, and repositories

Menu and theme objects provide framework-level composition. Value objects and repository/model bases provide BlueCore projections over DevElation data and service capabilities. These classes do not define product navigation, presentation policy, or deployment storage.

## Adapters and integrations

### Gateways

Gateway classes adapt request processing for authentication, caching, CORS, CSRF, and dynamic handlers. `GatewayDenied` is a control-flow boundary: the gateway owns the response, while `Engine` records denial and stops route execution.

### Declarative mapping

[`DeclarativeIntegrationMapper`](src/BlueCore/Integration/Vibe/DeclarativeIntegrationMapper.php) converts neutral arrays or objects into model, gateway, and application registration configuration. It does not parse a source language or execute host commands.

### Optional Presence bridge

[`PresenceBridgeFactory`](src/BlueCore/Integration/Presence/PresenceBridgeFactory.php) detects optional Presence contracts before creating [`PresenceAuthenticationBridge`](src/BlueCore/Integration/Presence/PresenceAuthenticationBridge.php). Missing contracts fail with a stable availability reason rather than a partial bridge.

### Generation

[`GeneratorFactory`](src/BlueCore/Generation/GeneratorFactory.php) selects generator implementations. Code and copy providers enter through package interfaces, allowing provider-free tests and host-supplied adapters. BlueCore does not own provider transport, billing, model policy, or a standalone CLI.

## Persistence and services

- SQL model and repository classes delegate database behavior through DevElation connections.
- `ModelSQLite` and `SQLiteModelStore` retain the model-facing SQLite projection documented in [docs/sqlite-model-boundary.md](docs/sqlite-model-boundary.md).
- `JsonInstallationCheckpointStore` is the first-party durable checkpoint adapter for resumable installation plans.
- Migration history is stored by datasource behavior and must remain recoverable when an operation fails.
- BlueCore defines service names and registration vocabulary; hosts supply concrete service implementations and environment configuration.

BlueCore has no framework-owned queue or background worker.

## Principal control flows

### Application request

1. A host creates or resolves its concrete `Engine`.
2. `bootstrap()` initializes security, loads configuration, and discovers helpers and mappings.
3. `process()` executes configured gateways and records a `GatewayDenied` boundary when a gateway stops dispatch.
4. `run()` delegates application execution when the request was not denied.
5. Lifecycle hooks publish bounded success or failure evidence around each phase.

### Extension registration

1. A registrar or active add-on receives the application and a `RegistrationPlan`.
2. Entries pass through type, name, section, and filter validation.
3. The application applies services, delegates, bindings, themes, and add-ons.
4. Failures retain phase and binding diagnostics and remain retryable where documented.

### Installation

1. A host defines stages and optional validation/approval requirements.
2. `prepare()` creates and optionally persists a plan.
3. `execute()` runs incomplete stages in order and checkpoints each outcome.
4. A failed stage stops execution and returns recovery guidance.
5. `resume()` restores a persisted plan and skips completed successful stages.

### Datasource migration

1. The manager discovers eligible files from its configured directory.
2. A bounded plan filter may reorder or remove discovered entries.
3. Files execute in plan order and emit per-item results.
4. Successful work updates migration history; failed or unattempted work remains available for recovery.

## Runtime and failure boundaries

- PHP 8.2+ and Composer 2.x define the supported runtime baseline.
- Exceptions remain the failure boundary for invalid configuration, unsafe files, invalid hook/filter results, and unavailable optional contracts.
- Structured operation results are used where callers need partial-success, no-op, retry, or next-action evidence.
- Global error/shutdown handling reports fatal categories without reclassifying handled or suppressed nonfatal history.
- Optional services and provider integrations remain opt-in and must not make clean provider-free tests fail.

## Security and configuration boundaries

- Credentials and secrets enter through host environment/configuration, never committed defaults.
- Hook summaries omit sensitive payloads, approval evidence, response/template bodies, and storage handles.
- Passive add-on contributions reject executable values.
- Registration and declarative mapping do not grant host authority or execute undeclared effects.
- Composer project promotion preserves existing root files instead of overwriting them.

## Test strategy and evidence

- Unit tests mirror source areas under `tests/BlueCore/`, `tests/Helpers/`, and `tests/Utils/`.
- Provider-free fixtures cover lifecycle, registration, installation, gateway, integration, generation, model, and helper boundaries.
- `tests/Integration/project-installer-parity.php` verifies clean Composer install, update, repeat-install, and uninstall behavior.
- Optional service tests are enabled only when their required extensions and environment variables are available.
- The baseline commands are documented in [tests.md](tests.md).

## Decisions and tradeoffs

- DevElation remains the primary primitive and service layer; BlueCore avoids duplicating generic upstream behavior.
- Structured arrays are retained for operational receipts where backward-compatible callers may ignore return values.
- Host-defined installation and registration keep BlueCore flexible, at the cost of requiring hosts to define product policy explicitly.
- Repository documentation and examples are currently excluded from package archives, reducing distribution size but requiring consumers to consult the matching repository tag.

## Unresolved risks

- The alpha line has not declared a stable compatibility window.
- Some older source areas predate the current structured lifecycle and strict typing conventions.
- Migration revert outcomes and history retention remain tracked in issue #99.
- Optional generation and integration adapters require consumer-level compatibility evidence before adoption claims.
