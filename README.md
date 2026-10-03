# BlueCore

BlueCore is a modular PHP 8.2+ application framework and Composer plugin built on [DevElation](https://github.com/BlueFissionTech/develation). It provides framework-owned lifecycle, registration, installation, gateway, model, template, add-on, and generation contracts while leaving product policy and deployment decisions to application hosts.

The current `0.1.x` line is alpha software intended for integration testing. Pin an explicit version constraint and verify the behaviors your application uses before adopting a new release.

## Requirements

- PHP 8.2 or newer
- Composer 2.x with project-approved plugin execution
- The platform requirements resolved by Composer for the selected dependency set

| Capability | Runtime or extensions | Required or optional |
| --- | --- | --- |
| Framework/package installation | PHP 8.2+, Composer 2.x and plugin API 2.x | Required |
| Current spreadsheet dependency | DOM, Fileinfo, Filter, GD, Iconv, Libxml, SimpleXML, XML, XMLReader, XMLWriter, ZIP, Zlib; Ctype and Mbstring requirements may be satisfied by selected polyfills | Required by the resolved dependency set even when spreadsheet APIs are unused |
| Test tooling | Extensions required by the resolved PHPUnit release, including DOM, Libxml, Mbstring and XMLWriter | Required for development tests |
| SQLite models | SQLite3 | Optional; required when those APIs/tests are used |
| Other service adapters/providers | Matching upstream extensions, services and host configuration | Optional; see the testing guide |

This matrix summarizes the inspected dependency set. Composer's platform check
is authoritative for the version selected by your project; requirements can
change when that dependency set changes.

Optional capabilities have additional requirements:

- `ModelSQLite` requires the PHP SQLite3 extension.
- SQL-backed datasource behavior requires the matching DevElation connection and service configuration.
- The authenticated Presence bridge requires `bluefission/presence`; it is not installed by default.
- Generation providers are injected by the host. BlueCore does not declare an AI provider runtime or bundled command-line application.

Validate the installed platform with:

```bash
composer check-platform-reqs
```

## Installation

Install the current alpha line through Composer:

```bash
composer require bluefission/bluecore:^0.1.4@alpha
```

BlueCore is a Composer plugin. Its plugin registers installers for add-on, theme, and `opus-project` packages. Composer packages of type `opus-project` install into `core/` and promote new project files without overwriting existing root files. Set `OPUS_STANDALONE=1` when a project package must remain under `vendor/`.

The package archive intentionally omits repository-only development material such as `docs/`, `examples/`, `tests/`, and `SPEC.md`. Use the repository tag matching the installed version when you need those materials.

## Getting started

After installation, package classes and the global helper files are available through Composer autoloading. This example uses the provider-free menu model:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use BlueFission\BlueCore\Menu;
use BlueFission\BlueCore\MenuItem;

$menu = new Menu('Workspace');
$menu->addItem(new MenuItem(
    'Dashboard',
    '/dashboard',
    'operator',
    'workspace',
    'dashboard.view'
));

foreach ($menu->getItems() as $item) {
    echo $item->getLabel() . PHP_EOL;
}
```

Runnable, provider-free examples are documented in [examples/README.md](examples/README.md).

## Framework capabilities

### Application lifecycle

`BlueFission\BlueCore\Engine` extends the DevElation application service with bootstrap, process, run, denial, dependency-resolution, configuration, extension, and theme lifecycle behavior. Stable filters and actions are documented in [DevElation hooks](docs/develation-hooks.md).

### Registration and extensions

`RegistrationPlan` and the registration contracts provide package-owned vocabulary for services, delegates, bindings, themes, and add-ons. Hosts retain ownership of concrete implementations and activation policy. See [extension registration contracts](docs/extension-registration-contracts.md).

### Installation orchestration

`InstallationExecutor` composes host-defined stages, optional validation and approval gates, structured outcomes, and optional checkpoint/resume behavior. BlueCore does not prescribe product questions, tenant provisioning, user onboarding, or deployment transitions.

### Datasources and models

`DatasourceManager` discovers and executes migration and population files with structured lifecycle evidence. BlueCore includes SQL and SQLite model projections; generic storage behavior remains owned by DevElation. See [the SQLite model boundary](docs/sqlite-model-boundary.md).

### Gateways, responses, and templates

Gateway classes cover authentication, cache, CORS, CSRF, and dynamic routing boundaries. `GatewayDenied` lets a gateway retain its response while stopping controller execution. Composer-loaded helpers provide response, template, path, storage, and error-reporting surfaces with bounded lifecycle hooks.

### Declarative integration

`DeclarativeIntegrationMapper` maps neutral declarations into BlueCore model, gateway, and application registration configuration. Parsing languages, source-file formats, compilation, and host execution remain outside this package. See [declarative integration contracts](docs/declarative-integration-contracts.md).

### Generation

BlueCore provides generator interfaces, a `GeneratorFactory`, scaffold helpers, and injectable code/copy provider contracts. The library does not ship a standalone CLI entrypoint or claim compatibility with an undeclared AI runtime. Consumers must inject and test any provider implementation they adopt.

## Documentation

- [Specification](SPEC.md)
- [Architecture](ARCHITECTURE.md)
- [Roadmap](ROADMAP.md)
- [Testing guide](tests.md)
- [Contribution guide](CONTRIBUTING.md)
- [Collaboration and ownership](COLLABORATION.md)
- [Examples](examples/README.md)
- [Release notes](docs/releases/)

## Development

```bash
composer lint
vendor/bin/phpunit --do-not-cache-result
composer test:installer
```

Optional integration tests remain opt-in. See [tests.md](tests.md) for requirements and environment variables.

## Security

Do not commit secrets. Keep service credentials in environment variables, keep optional service tests disabled on clean setups, and treat host authorization as a host responsibility rather than widening BlueCore's framework authority.

Report security-sensitive concerns privately to the maintainers instead of publishing credentials or exploit details in an issue.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Changes should remain within BlueCore's reusable framework boundary and include focused tests plus exact validation commands.

## License

BlueCore is licensed under the [MIT License](LICENSE).

## Support

Use the [GitHub issue tracker](https://github.com/BlueFissionTech/bluecore/issues) for reproducible BlueCore defects and reusable feature requests.
