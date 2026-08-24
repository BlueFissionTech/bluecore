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
- `loadActivatedAddOns(?Application $application = null, ?RegistrationPlan $plan = null): array`

Lifecycle methods return structured arrays so callers can compose results, log decisions, and avoid process termination.

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
