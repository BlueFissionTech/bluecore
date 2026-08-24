# Extension Registration Contracts

BlueCore extension registration is package-level framework behavior. It should stay generic enough for any BlueCore consumer while remaining prescriptive about the lifecycle shape.

## Registration Lifecycle

An extension can describe its contribution through a `RegistrationPlan`:

- services: reusable service names and definitions
- delegates: callable or class-backed delegation entries
- bindings: symbolic binding names mapped to implementation definitions
- themes: named theme locations
- addons: add-on metadata and lifecycle entries

Registrars implement `BlueFission\BlueCore\Contracts\IApplicationRegistrar` and receive both the application instance and a mutable `RegistrationPlan`.

## Add-on Lifecycle

Add-on lifecycle managers implement `IAddOnLifecycleManager`:

- `install($name, bool $installDependencies = false): array`
- `installAll(bool $installDependencies = false): array`
- `uninstall($addOnId, bool $removeDependencies = false): array`
- `activate($addOnId): array`
- `activateAll(): array`
- `deactivate($addOnId): array`
- `migrate($addOnId): array`
- `deactivateAll(): array`
- `loadActivatedAddOns(?Application $application = null, ?RegistrationPlan $plan = null): array`

Lifecycle methods return structured arrays so callers can compose results, log decisions, and avoid process termination.

Install and uninstall hooks are optional. When a reachable primary file has no
compatible hook callable, the hook result is `ok=true`, `status=skipped`, and
`optional=true`. A missing or unsafe configured primary file remains blocking, as
does an exception thrown by a discovered hook. Blocking hook failures stop before
registration state is written or removed and retain structured retry diagnostics.

Datasource migration and population are reported as distinct result blocks. Population
stops on the first material generator failure, identifies completed and failed
generators, and uses `review_population_failure` as its next action because BlueCore
cannot guarantee that an arbitrary generator is retry-idempotent. Installation does
not continue to hooks or registration writes after such a failure.

`migrate()` refreshes the datasource structure of an already-installed add-on
without installing dependencies or populating data. Migration history is
filtered by the add-on batch before pending deltas are discovered. Successful
deltas are idempotent on repeat; failed deltas stop the batch, retain their
exception diagnostics, and report `retry_migrate` without marking the
lifecycle operation complete.

Bulk lifecycle operations normalize DevElation `Collection` and `Group`
containers through the collection API before processing their rows. Each result
retains the exact persisted row used for the operation, including identifiers,
namespace separators, paths, primary files, and activation state. A malformed
row or failed storage write makes the aggregate result fail while preserving
the per-row diagnostic and leaving other rows visible to the caller.

An active add-on primary file may return a callable registration factory. When
the application and baseline registration plan are supplied together, BlueCore
invokes that factory with `(Application $application, RegistrationPlan $plan)`
once per manager lifecycle. A factory may accept fewer arguments when it does
not need the full context. Repeated loading reports `already_registered`
without executing the factory again. Calls without a registration context keep
the callable available and report `pending`, while legacy primary files that do
not return a callable report `not_applicable`.

Factory failures report the registration stage, primary file, original
exception class, and message. A failed factory is not marked as registered, so
a later lifecycle call can retry it.

## Theme Registry

Theme registries implement `IThemeRegistry`:

- `registerTheme(string $name, string $location = null): mixed`
- `theme(string $name): mixed`

The interface intentionally avoids prescribing a concrete theme storage backend. BlueCore owns the contract, while applications decide how registered themes are stored and exposed.

## Ownership Boundary

BlueCore owns the registration vocabulary, lifecycle result shape, and generic plan object. Applications own concrete service definitions, concrete bindings, route surfaces, and deployment-specific activation policy.
