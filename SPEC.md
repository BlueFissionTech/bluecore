# BlueCore Specification

## Purpose

BlueCore is a reusable PHP 8.2+ application framework and Composer plugin. It builds on DevElation to provide application lifecycle, registration, installation, gateway, model, datasource, template, add-on, and generation contracts that hosts can compose without transferring product or deployment policy into the library.

## Scope

BlueCore owns:

- application bootstrap, processing, run, denial, and lifecycle hook boundaries;
- named service, delegate, binding, theme, and add-on registration plans;
- Composer installers for BlueCore add-on, theme, and `opus-project` package types;
- resumable host-defined installation stages and structured stage outcomes;
- datasource migration/population orchestration and model-facing SQL/SQLite projections;
- framework gateways, response/template helpers, menus, themes, repositories, and value objects;
- neutral declarative mapping into BlueCore configuration;
- provider-injected generation interfaces and factories;
- cross-platform path and file helpers backed by DevElation primitives.

## Non-goals

BlueCore does not own:

- application-specific routes, product workflows, tenant or user provisioning, or deployment policy;
- provider transport, model selection, prompts, billing, or a bundled AI runtime;
- a standalone command-line application or host command-authority envelope;
- an integration language parser, compiler, or source-file format;
- generic storage primitives already owned by DevElation;
- concrete identity, authorization, session, audit, or UI policy owned by optional integrations or application hosts;
- automatic dependency, service, credential, or environment mutation.

## Users and calling systems

- PHP application hosts that need a reusable application runtime and extension lifecycle.
- Package authors that publish BlueCore add-ons, themes, or Opus-style project packages.
- Framework integrators that map neutral declarations into BlueCore models, gateways, and registrations.
- Contributors maintaining BlueCore's contracts, tests, examples, and release evidence.

## User stories and acceptance criteria

### Host lifecycle

As an application host, I can bootstrap and run a concrete `Engine`, resolve application-owned bindings, and observe bounded lifecycle hooks.

Acceptance criteria:

- Lifecycle failures preserve the original exception while emitting sanitized failure metadata.
- Gateway denial stops controller execution without replacing the response produced by the gateway.
- Hook recursion is bounded and failure hooks cannot opt into recursive dispatch.

### Extension registration

As an extension author, I can contribute reusable services, delegates, bindings, themes, and add-ons through a `RegistrationPlan`.

Acceptance criteria:

- Invalid sections, names, filter return types, or cross-section moves fail before mutating the plan.
- Active add-on factories execute at most once per manager lifecycle and remain retryable after failure.
- Passive contributions reject callable and object values before use.

### Installation

As a host, I can compose ordered installation stages, require host approval, checkpoint outcomes, and resume without repeating completed stages.

Acceptance criteria:

- Stage results expose stable success, change, skip, evidence, error, exception, and next-action fields.
- Host context and approval evidence are not exposed through global lifecycle actions.
- A failed stage is checkpointed and returns recovery guidance without claiming completion.

### Datasource and model behavior

As a framework consumer, I can run migrations and population work with inspectable results and use package-owned SQL or SQLite model projections.

Acceptance criteria:

- Planning filters may reorder or remove discovered work but cannot inject undiscovered files or alter framework-owned batch state.
- Successful migration history is retained and repeated work is idempotent where the contract declares it.
- SQLite results are materialized into package collections and expose bounded diagnostics.

### Templates and responses

As a host, I can delegate template rendering or use the DevElation fallback pipeline and can observe response/template lifecycle events without exposing bodies or secrets.

Acceptance criteria:

- Template and response filters fail closed on invalid return values.
- Error-report hooks are observational and cannot replace logging, status, output, or exit behavior.
- Missing optional rendering services fall back only to documented package behavior.

### Generation

As a consumer, I can inject generation providers into BlueCore generators and test generation behavior without network access.

Acceptance criteria:

- Provider interfaces remain independent of a specific model vendor or transport.
- Provider-free fixtures can exercise factory selection, output paths, and failure handling.
- Documentation does not claim a bundled CLI or undeclared AI runtime.

## Public interfaces

The principal public surfaces are:

- `BlueFission\BlueCore\Engine` and framework lifecycle hook constants;
- `RegistrationPlan`, `RegistrationEntry`, and contracts under `src/BlueCore/Contracts/`;
- `InstallationExecutor`, `InstallationPlan`, and `IInstallationCheckpointStore`;
- `AddOnManager`, `DatasourceManager`, and `NavMenuManager`;
- gateways under `src/BlueCore/Gateway/`;
- SQL/SQLite models and repositories under `src/BlueCore/Model/` and `src/BlueCore/Repository/`;
- generation interfaces and factories under `src/BlueCore/Generation/`;
- global helpers loaded from `src/Helpers/`;
- installer classes under `src/BlueCore/Installers/`.

Detailed lifecycle value shapes are documented in [docs/develation-hooks.md](docs/develation-hooks.md), [docs/extension-registration-contracts.md](docs/extension-registration-contracts.md), and [docs/declarative-integration-contracts.md](docs/declarative-integration-contracts.md).

## External integrations

- DevElation is the primary runtime dependency and owns shared primitives, behaviors, services, connections, templates, and generic storage.
- Composer activates BlueCore's package installers.
- Presence is an optional authenticated host bridge, discovered only when its contracts are installed.
- Host-supplied generation providers implement BlueCore interfaces; provider transport remains outside BlueCore.

## Runtime and configuration expectations

- PHP 8.2+ and Composer 2.x are required.
- Composer platform checks remain enabled.
- Optional integrations and service-backed tests are opt-in and configured through environment variables.
- `OPUS_STANDALONE=1` keeps an `opus-project` package under `vendor/`; otherwise it installs to `core/` with non-destructive root promotion.
- Secrets, credentials, mutable local paths, and private assets must not be committed.

## Quality, security, and operational constraints

- Source remains cross-platform and avoids OS-specific process utilities.
- Optional service failures must not break clean provider-free test runs.
- Public hooks expose bounded, sanitized summaries rather than request bodies, response bodies, credentials, approval evidence, or storage handles.
- Protected branches change through pull requests only.
- Behavioral changes require focused tests and a full-suite regression run proportional to risk.

## Open maintainer questions

- What compatibility evidence and stability criteria define the first non-alpha release?
- Should repository documentation and examples remain excluded from Composer distribution archives, or should a smaller installed guide be shipped?
- Which generator providers, if any, should become separately versioned optional adapters rather than host-only implementations?
- When should the local SQLite model layer delegate more behavior directly to the evolving DevElation SQLite adapter?
