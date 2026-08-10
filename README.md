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
composer require bluefission/bluecore:^0.1.0-alpha
```

The alpha line is intended for integration testing while the public API is finalized. Pin an explicit alpha constraint in reproducible environments.

## Usage

### Event Management

BlueCore's event management system allows you to hook into various events and filters, making it easy to extend and customize the framework's behavior.

### Plugin System

The plugin-based architecture allows for seamless feature additions and management. You can create plugins to extend the core functionality without modifying the core files directly.

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
