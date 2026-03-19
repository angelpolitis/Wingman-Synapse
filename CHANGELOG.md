# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] – 2026-03-19

### Added

**Container core**

- `Container` class implementing `ContainerInterface` and the PSR-11 bridge interface.
- Three binding modes via `BindingMode` enum: `Singleton`, `Scoped`, and `Transient`.
- `bind()`, `bindSingleton()`, `bindTransient()`, `bindLazy()` and their conditional `*If()` variants.
- `get()` — resolves an abstract, walking the scope stack for the nearest cached instance, honouring
  singletons, scoped lifetimes, lazy-scoped caching, and transient semantics.
- `make()` — always constructs a fresh instance, bypassing every instance cache. Accepts per-call
  constructor parameter overrides that are rolled back after resolution.
- `has()` — returns `true` for any resolvable identifier (bound, aliased, instantiable class, or factory).
- `hasBinding()` — strict check that excludes bare autoloadable class names.
- `alias()` — maps an alternative identifier to an existing abstract.
- `rebind()` — clears an existing registration and re-registers it atomically.
- `forget()` — removes an abstract from all registries, caches, tag maps, and callback tables.
- `fork()` — creates an isolated child container that inherits all configuration but maintains its own
  independent instance store.
- `call()` — invokes a callable with fully-resolved, contextual-binding-aware parameter injection.
- `createProxy()` — builds a lazy proxy; on PHP 8.4+ a native `ReflectionClass::newLazyProxy()` is used
  for non-final, non-abstract concretes; earlier PHP or incompatible classes fall back to `LazyProxy`.
- `warmup()` — pre-populates the reflection cache and runs attribute scanning for all registered concretes,
  eliminating per-request reflection overhead.
- `exportCache()` / `loadCache()` — serialises and restores bindings, aliases, injection maps, and the
  attribute cache to/from a PHP file, enabling zero-reflection boots.
- `loadConfig()` — delegates to `ConfigLoader` to drive the container from a JSON manifest file.
- `GLOBAL_SCOPE` class constant for the always-present root scope.
- `setStrict()` — when enabled, `get()` and `make()` throw `NotFoundException` for any abstract with no
  explicit binding registered.
- `setInjectProtected()` — opt-in injection into protected properties.
- `setInjectableOnlyWithAttributes()` — restricts property injection to properties carrying `#[Inject]`.
- `setParameter()` / `addParameter()` — global and per-class scalar fallback values for constructor
  parameters.
- Reflection cache (`reflectClass()`) to prevent redundant `ReflectionClass` instantiations.
- Automatic attribute scanning via `registerAttributes()` on first resolution of any concrete.

**Scopes**

- `registerScope()`, `enterScope()`, `exitScope()` — nestable scope stack with automatic cache clearance
  on exit.
- Per-scope instance cache (`$scopeInstances`) and per-scope lazy cache (`$lazyScopeCache`).
- Per-scope tag registry (`$scopeTags`) merged with the global tag registry in `getByTag()`.
- Per-scope dependency graph (`$scopedDependencyGraph`) queryable alongside the global graph.

**Contextual bindings**

- `setContextualBinding()` — registers a per-consumer, per-abstract override directly.
- `forConsumer()` — returns a `ContextualBindingBuilder` for the fluent `->needs()->give()` chain.
- `ContextualBindingBuilder` class with `needs()` and `give()` methods.
- Scope-locked contextual bindings: an override can be restricted to a specific scope name.

**Tags**

- `tag()` — associates an abstract with a named tag at a given priority.
- `getByTag()` — resolves all services under one or more tags, sorted by descending priority, with an
  optional filter callable. Correctly honours singleton lazy, scoped lazy, and standard bindings with
  per-tag-scope caching.

**Lifecycle hooks and decorators**

- `beforeResolving()` — registers a callback invoked before every `get()` / `make()` call (used
  internally by `ProviderManager` to trigger deferred provider boot).
- `resolving()` — registers a callback invoked immediately after construction, before extenders run.
- `resolved()` — registers a callback invoked after all extenders have been applied.
- `onBind()` — registers a callback invoked whenever a specific abstract (or `'*'` for all) is bound.
- `extend()` — registers a decorator callable that transforms each resolved instance of an abstract.
- Wildcard `'*'` support for `resolving()`, `resolved()`, and `onBind()`.

**Attribute-driven registration**

- `#[Service]` — marks a class as an auto-registered service (singleton by default, optionally scoped).
- `#[Bind]` — declares which interface or abstract a class binds to, with optional singleton, scope, and
  lazy flags.
- `#[Context]` — declares a contextual binding override for a named consumer and abstract, optionally
  scoped.
- `#[Inject]` — applied to a constructor parameter or property to override the resolved service by
  explicit identifier or by tag.
- Interface alternatives: `Interfaces\Service`, `Interfaces\Bind`, `Interfaces\Context`.
- Trait alternatives: `Traits\Service`, `Traits\Bind`, `Traits\Context`.
- All three registration paths (attributes, interfaces, traits) are scanned once per concrete on first
  resolution and the result is stored in `$attributeCache`.

**Service providers**

- `Provider` abstract base class with `register()`, `boot()`, `$deferred`, `$provides`, `$dependsOn`,
  and `$priority` fields, plus `callAfterResolving()` and `mergeConfig()` convenience helpers.
- `ProviderInterface` contract.
- `ProviderManager` orchestrating registration and boot in dependency-first, priority-descending,
  class-name-ascending order.
- Deferred provider support: deferred providers are indexed by their provided abstracts and booted
  on-demand when the first matching abstract is resolved.
- Lifecycle event listeners: `onBooted()`, `onBooting()`, `onFailed()`, `onRegistered()`,
  `onRegistering()`.
- Circular and missing provider dependency detection during ordering.

**Invoker**

- `Invoker` class — fluent callable invocation with container-backed parameter resolution.
- `call()` — resolves all unspecified typed parameters from the container, respecting `#[Inject]`
  overrides, registered factories, external objects, and named parameter maps.
- `useExtraParams()` — supplies named scalar parameters as a secondary fallback.
- `useFactory()` — sets a per-invocation factory for object parameter creation.
- `useObjects()` — pre-supplies typed objects that are matched by class name.
- `useScope()` — enters and exits a named scope around the callable invocation.
- `useParams()` — sets the primary named parameter map.
- `Container::createInvoker()` factory method.

**Object hydrator**

- `ObjectHydrator` class — maps associative arrays to typed objects.
- `hydrate()` — constructor-first hydration with recursive nested DTO support; falls back to public
  property assignment when no constructor is declared.
- `hydrateMany()` — batch hydration from an array of data arrays.

**Dependency graph analysis**

- `getDependencyGraph()` / `getScopedDependencyGraph()` — returns the accumulated global or per-scope
  dependency graph.
- `getDependencies()` / `getDependents()` — queries the graph for a specific class.
- `getFullDependencyGraph()` — builds a rich, recursively expanded graph for a class, including binding
  metadata, tags, and subgraphs for all constructor and property dependencies.
- `detectCircularDependencies()` — runs DFS cycle detection over the global or current-scope graph.
- `printDependencyGraph()` — writes a human-readable summary to stdout.
- `getGraphAnalyser()` — returns a `GraphAnalyser` instance backed by the specified graph.
- `GraphAnalyser` class with `getDependencies()`, `getDependents()`, `detectCircularDependencies()`,
  and `printGraph()`.

**Configuration**

- `ConfigLoader` class — parses a JSON manifest and calls the appropriate container API methods.
- Supported manifest keys: `scopes`, `services`, `tags`, `contextual`, `parameters`, `aliases`.
- Per-service `constructorInjection` and `propertyInjection` maps inside the JSON config.
- Tag declarations support both flat lists (all at priority 0) and priority maps.
- `Container::loadConfig()` convenience wrapper.

**PSR-11 bridge**

- `Bridge\PSR\ContainerExceptionInterface` and `Bridge\PSR\NotFoundExceptionInterface` stubs that extend
  the real PSR interfaces when `psr/container` is installed, or are plain markers otherwise.
- `Bridge\PSR\ContainerInterface` stub extending `Psr\Container\ContainerInterface` when available.
- `Bridge\PSR\ContainerException` — concrete exception implementing `ContainerExceptionInterface`,
  extending `RuntimeException`. Thrown for resolution failures (non-instantiable class, circular
  dependency, unresolvable parameter, cache load failure).
- `Bridge\PSR\NotFoundException` — extends `ContainerException`, implements `NotFoundExceptionInterface`.
  Thrown for missing-identifier failures in strict mode and for unknown class names.

**Corvus signal bridge**

- `Bridge\Corvus\Emitter` stub — aliases the real `Wingman\Corvus\Emitter` when Corvus is installed; falls
  back to a no-op stub so the container runs without any hard dependency on the event bus.
- `Signal` backed enum: `BOUND`, `SCOPE_ENTERED`, `SCOPE_EXITED`, `RESOLVING`, `RESOLVED`.
  Each case carries a dot-notation value consumed by Corvus listeners.

**Supporting classes and enums**

- `BindingMode` backed enum with `Singleton`, `Scoped`, `Transient` cases and a `resolve()` helper.
- `Signal` backed enum (see above).
- `NodeState` enum used internally by `GraphAnalyser`'s DFS cycle detection.
- `TagScope` enum used internally by tag collection helpers.
- `LazyProxy` magic-method fallback proxy for PHP < 8.4 and final/abstract classes.

**Tests**

- 148 tests (Wingman Argus test harness) across 22 test files covering the full public API surface,
  including edge cases for every binding mode, scope lifecycle, contextual overrides, tag resolution,
  provider ordering, deferred boot, Invoker factories, ObjectHydrator nested DTOs, graph cycle
  detection, cache round-trips, strict mode, PSR contract compliance, and lazy-scoped tag caching.
