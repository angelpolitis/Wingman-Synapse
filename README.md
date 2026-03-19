# Wingman Synapse

A full-featured dependency injection container for PHP 8.1+, shipping with scoped lifetimes, contextual bindings, a provider lifecycle, attribute-driven auto-registration, optional PSR-11 compatibility, and an optional Corvus event-bus integration.

---

## Requirements

- PHP **8.1** or higher
- No mandatory third-party dependencies

Optional integrations:

| Package | Purpose |
| --- | --- |
| `psr/container` | Promotes the PSR bridge stubs to native `Psr\Container` contracts |
| `wingman/corvus` | Enables live signal emission for container lifecycle events |

---

## Installation

```bash
composer require wingman/synapse
```

---

## Quick Start

```php
use Wingman\Synapse\Container;

$container = new Container();

// Bind an interface to a concrete implementation.
$container->bind(LoggerInterface::class, FileLogger::class);

// Resolve — constructor dependencies are autowired automatically.
$logger = $container->get(LoggerInterface::class);

// Enter a scope, do work, then exit — all scoped instances are discarded on exit.
$container->enterScope("request");
$handler = $container->get(RequestHandler::class);
$container->exitScope();
```

---

## Features

| Feature | Description |
| --- | --- |
| **Three binding modes** | Singleton, Scoped, Transient — see [Bindings](docs/Container.md#binding-modes) |
| **Lazy bindings** | Defer instantiation until first access — see [Container](docs/Container.md) |
| **Scopes** | Partition instance lifetimes with a nestable scope stack — see [Scopes](docs/Scopes.md) |
| **Contextual bindings** | Give different concretes to different consumers — see [Contextual](docs/Contextual.md) |
| **Tags** | Group services under named tags with priority ordering — see [Tags](docs/Tags.md) |
| **Lifecycle hooks** | `beforeResolving`, `resolving`, `resolved`, `onBind` — see [Lifecycle](docs/Lifecycle.md) |
| **Decorators (extend)** | Wrap resolved instances without touching the binding — see [Lifecycle](docs/Lifecycle.md) |
| **Service providers** | Structured register/boot lifecycle with deferred support — see [Providers](docs/Providers.md) |
| **Attributes** | `#[Service]`, `#[Bind]`, `#[Context]`, `#[Inject]` — see [Attributes](docs/Attributes.md) |
| **Invoker** | Call any callable with full DI resolution — see [Invoker](docs/Invoker.md) |
| **Object hydrator** | Map plain arrays to typed DTOs — see [ObjectHydrator](docs/ObjectHydrator.md) |
| **Graph analysis** | Detect cycles, query dependencies/dependants — see [GraphAnalysis](docs/GraphAnalysis.md) |
| **JSON configuration** | Drive the container entirely from a JSON file — see [Configuration](docs/Configuration.md) |
| **Cache warmup** | Export a serialised state snapshot for zero-overhead boots — see [Configuration](docs/Configuration.md) |
| **Container forking** | Spawn isolated child containers that inherit parent config — see [Container](docs/Container.md) |
| **PSR-11 bridge** | Optional native `Psr\Container` interface compliance — see [PSR-11](docs/PSR11.md) |
| **Corvus signals** | Lifecycle events over the Corvus event bus — see [Signals](docs/Signals.md) |

---

## Documentation

| Document | Coverage |
| --- | --- |
| [Container](docs/Container.md) | Core API: bind, get, make, alias, extend, fork, proxy, warmup |
| [Scopes](docs/Scopes.md) | Scope stack management, scoped lifetimes, scope events |
| [Contextual Bindings](docs/Contextual.md) | Consumer-specific overrides, fluent builder, scope-locked contextual |
| [Tags](docs/Tags.md) | Tagging services, resolving by tag, priority ordering, filtering |
| [Lifecycle Hooks](docs/Lifecycle.md) | beforeResolving, resolving, resolved, onBind, extend |
| [Service Providers](docs/Providers.md) | Provider base class, ProviderManager, deferred providers, lifecycle events |
| [Attributes](docs/Attributes.md) | #[Service], #[Bind], #[Context], #[Inject], interface/trait alternatives |
| [Invoker](docs/Invoker.md) | Fluent callable invocation with DI, factories, and scope injection |
| [Object Hydrator](docs/ObjectHydrator.md) | Mapping arrays to typed value objects (DTOs) |
| [Graph Analysis](docs/GraphAnalysis.md) | Dependency graphs, cycle detection, full dependency trees |
| [Configuration](docs/Configuration.md) | JSON config file format, cache export/import |
| [PSR-11 Bridge](docs/PSR11.md) | PSR-11 compatibility, ContainerException, NotFoundException |
| [Corvus Signals](docs/Signals.md) | Signal enum, event payloads, Corvus integration |

---

## Licence

This project is licensed under the **Mozilla Public License 2.0 (MPL 2.0)**.

Wingman Synapse is part of the **Wingman Framework**, Copyright (c) 2018–2026 Angel Politis.

For the full licence text, please see the [LICENSE](LICENSE) file.
