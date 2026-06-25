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

Lifecycle methods return structured arrays so callers can compose results, log decisions, and avoid process termination.

## Theme Registry

Theme registries implement `IThemeRegistry`:

- `registerTheme(string $name, string $location = null): mixed`
- `theme(string $name): mixed`

The interface intentionally avoids prescribing a concrete theme storage backend. BlueCore owns the contract, while applications decide how registered themes are stored and exposed.

## Ownership Boundary

BlueCore owns the registration vocabulary, lifecycle result shape, and generic plan object. Applications own concrete service definitions, concrete bindings, route surfaces, and deployment-specific activation policy.
