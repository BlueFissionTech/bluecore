# BlueCore Framework

Welcome to the BlueCore framework! BlueCore is a powerful, modular, and event-driven framework designed to build robust web applications and software solutions with a strong emphasis on AI integration. Below you will find an overview of the core functionality, usage, and intention of the BlueCore framework.

## Overview

### Purpose

BlueCore provides core functionality and architecture for modular, scalable web applications. Its design emphasizes flexibility, extensibility, event-driven services, and optional intelligence integrations.

### Key Features

- **Modular Event-Driven Architecture**: Utilizes hooks for filters and actions to enable flexible and powerful event handling.
- **Plugin-Based System**: Supports drop-in feature enhancements and management, allowing for easy extensibility without modifying core code.
- **AI-First Design**: Natively compatible with AI integrations, streamlining the process of building AI-powered applications.
- **Code Generators and CLI Tools**: Includes a variety of command-line tools to streamline and simplify development processes.

## Installation

BlueCore requires PHP 8.2 or newer. Install the current alpha release through Composer:

```bash
composer require bluefission/bluecore:^0.1.2@alpha
```

The alpha line is intended for integration testing while the public API is finalized. Pin an explicit alpha constraint in reproducible environments.

### Project Packages

Composer packages with the `opus-project` type install into `core/` by default. Project directories and root configuration files are promoted as non-destructive overrides: existing root files are preserved, while files introduced by later project versions are added during updates. Source and distribution installs follow the same lifecycle.

Set `OPUS_STANDALONE=1` to install a project package under `vendor/` without promoting root overrides.

### Optional Installation Orchestration

Application hosts can compose a resumable sequence without adopting a BlueCore-defined installation schema. The plan context, validation rules, approval evidence, stage metadata, ordering, and product transitions remain host-owned:

```php
use BlueFission\BlueCore\Installation\InstallationExecutor;
use BlueFission\BlueCore\Installation\JsonInstallationCheckpointStore;

$executor = (new InstallationExecutor(
    new JsonInstallationCheckpointStore($checkpointDirectory)
))
    ->validator(fn (array $context) => $hostPolicy->validate($context))
    ->requireApproval()
    ->stage('registration', fn ($plan) => $registrar->apply($plan->context()), [
        'owner' => 'host',
    ])
    ->stage('lifecycle', fn ($plan) => $lifecycle->apply($plan->context()));

$plan = $executor->prepare($hostDefinedContext);

$plan->approve($hostApprovalEvidence);
$result = $executor->execute($plan);
```

The checkpoint store and approval gate are optional. Without a checkpoint store, the executor can run an in-memory plan but cannot resume it later. BlueCore records structured stage outcomes and avoids repeating successful stages after a resume; it does not prescribe installation questions, required fields, defaults, skips, permissions, or a product-specific stage graph.

The checkpoint records supplied values, accepted defaults, explicit skips, permission review, per-stage evidence, retry guidance, and terminal status. `resume($planId)` restores a failed or interrupted plan; completed successful stages are not repeated. Question rendering, branding, tenant creation, administrator provisioning, login transitions, and onboarding navigation remain host responsibilities.

## Usage

### Event Management

BlueCore's event management system allows you to hook into various events and filters, making it easy to extend and customize the framework's behavior.

### Plugin System

The plugin-based architecture allows for seamless feature additions and management. You can create plugins to extend the core functionality without modifying the core files directly.

### Gateway Denials

Gateways may stop a mapped controller by producing their response and throwing `GatewayDenied`. `Engine` records the denial and skips route execution without replacing the gateway-owned response. Successful gateways continue through the standard dispatch path.

```php
use BlueFission\BlueCore\Gateway\GatewayDenied;

http_response_code(403);
echo 'Forbidden';

throw new GatewayDenied('Forbidden', 403);
```

Hosts can inspect `Engine::denied()` and `Engine::denial()` after processing when denial metadata is needed.

### Optional Presence Bridge

Applications with `bluefission/presence` installed may create the authenticated host bridge through the availability-safe factory:

```php
use BlueFission\BlueCore\Integration\Presence\PresenceBridgeFactory;
use BlueFission\Presence\Bridge\BridgeContext;

$bridge = PresenceBridgeFactory::make();
$context = new BridgeContext();
$context->host = 'bluecore';
$context->authenticator = $authenticator;
$context->session = [
    'id' => $sessionId,
    'type' => 'browser',
];
$context->metadata = [
    'tenant_id' => $tenantId,
    'action' => 'authenticate',
];

$result = $bridge->bind($context);
```

The bridge maps neutral identity and session values into Presence `Principal`, `AuthResult`, `Session`, and `Participant` contracts. An optional `annex_manifest` is ingested through Presence's Annex adapter. Unsupported or incomplete contexts return an unbound result with a stable reason and audit block.

When Presence is unavailable, `PresenceBridgeFactory::make()` throws `PresenceBridgeUnavailable` with the `presence_contracts_unavailable` reason and the missing contract names. Bridge lifecycle extension points are exposed as `bluecore.presence.bridge.input`, `.before`, `.output`, and `.after` DevElation hooks.

### AI Integration

BlueCore is designed to integrate seamlessly with AI libraries such as Automata (`bluefission/automata`), providing native compatibility and simplifying the process of building AI-powered applications.

### Command Line Tools

BlueCore includes a set of command-line tools to assist with various development tasks, from generating code to managing configurations.

## Core Components

### ValueObject

The `ValueObject` class is used to store and manage values as an object, providing easy access to its properties.

### Theme

The `Theme` class manages theme-related properties and paths, allowing for easy customization of the application's look and feel.

Applications may register a rendering service under the canonical `template` service name. The service must expose `render(string $themeName, string $file, array $data): string`; the global helper preserves the same arguments and returns its result directly.

```php
$app->delegate('template', $renderer);

$output = template('admin', 'dashboard.vibe', [
    'title' => 'Dashboard',
]);
```

When no compatible service is registered, `template()` renders the selected theme with DevElation's `HTML\Template` and `Parsing\Parser` pipeline. The fallback configures the theme directory for template includes and its `modules` directory for module includes.

### MenuItem and Menu

The `MenuItem` and `Menu` classes represent individual items and collections of items in a menu, respectively. These classes facilitate the creation and rendering of dynamic menu structures.

### Engine

The `Engine` class sets up and starts the BlueFission application, loading configurations and auto-discovering helpers and mappings. It serves as the main entry point for initializing and running the application.

## Intention and Target Framework

### Flexibility and Extensibility

BlueCore is designed with a modular event-driven architecture that allows developers to easily extend and customize the framework through hooks and actions. This flexibility ensures that the framework can adapt to a wide range of use cases and project requirements.

### AI Integration

With its AI-first design, BlueCore is particularly well-suited for applications that leverage artificial intelligence. The framework's native compatibility with AI libraries and models simplifies the integration process, making it easier for developers to build sophisticated AI-powered applications.

### Developer Productivity

BlueCore includes a variety of code generators and command-line tools that streamline the development process. These tools help developers quickly scaffold new components, manage configurations, and automate repetitive tasks, thereby enhancing productivity and reducing development time.

## Contributing

We welcome contributions to improve BlueCore. If you would like to contribute, please follow the guidelines in our [contributing guide](https://github.com/bluefission/bluecore/CONTRIBUTING.md).

## License

BlueCore is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Support

If you have any questions or need support, please open an issue on our [GitHub repository](https://github.com/bluefission/bluecore/issues).
