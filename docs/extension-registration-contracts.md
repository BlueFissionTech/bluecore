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
- `uninstall($addOnId, bool $removeDependencies = false): array`
- `activate($addOnId): array`
- `deactivate($addOnId): array`
- `migrate($addOnId): array`

Lifecycle methods return structured arrays so callers can compose results, log decisions, and avoid process termination.

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

## Theme Registry

Theme registries implement `IThemeRegistry`:

- `registerTheme(string $name, string $location = null): mixed`
- `theme(string $name): mixed`

The interface intentionally avoids prescribing a concrete theme storage backend. BlueCore owns the contract, while applications decide how registered themes are stored and exposed.

## Ownership Boundary

BlueCore owns the registration vocabulary, lifecycle result shape, and generic plan object. Applications own concrete service definitions, concrete bindings, route surfaces, and deployment-specific activation policy.
