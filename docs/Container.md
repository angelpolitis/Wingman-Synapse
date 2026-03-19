# Container

`Wingman\Synapse\Container` is the core class. It holds all bindings, resolves services, manages
scopes, and coordinates every other subsystem. The container autowires constructor parameters by
type, performs property injection, and tracks a live dependency graph as services are resolved.

---

## Creating a Container

```php
use Wingman\Synapse\Container;

$container = new Container();
```

The constructor creates an internal Corvus emitter (or a silent no-op when Corvus is absent).
No configuration is required.

---

## Binding Services

### `bind()`

```php
$container->bind(string $abstract, string|callable $concrete, array $options = []) : static
```

Registers an abstract-to-concrete mapping. The `$concrete` may be a fully-qualified class name or
a factory callable that receives the container as its sole argument and returns the instance.

`$options` keys:

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `mode` | `BindingMode\|string` | `BindingMode::Scoped` | Lifetime strategy |
| `lazy` | `bool` | `false` | Defer instantiation until first access |
| `scope` | `string\|null` | `null` (current scope) | Scope this binding belongs to |
| `tags` | `array` | `[]` | Tag associations — see [Tags](Tags.md) |

```php
$container->bind(LoggerInterface::class, FileLogger::class);

$container->bind(LoggerInterface::class, fn (Container $c) => new FileLogger('/var/log/app.log'), [
    'mode' => BindingMode::Singleton,
]);
```

### Binding Mode Shortcuts

```php
// One shared instance for the entire container lifetime.
$container->bindSingleton(LoggerInterface::class, FileLogger::class);

// A fresh instance on every get() call. Never cached.
$container->bindTransient(LoggerInterface::class, FileLogger::class);

// Singleton resolved lazily — built on first access, kept forever.
$container->bindLazy(LoggerInterface::class, FileLogger::class);
```

### Conditional Variants

Every bind method has a `*If(bool $condition, ...)` counterpart that is a no-op when
`$condition` is `false`:

```php
$container->bindSingletonIf($isProduction, CacheInterface::class, RedisCache::class);
$container->bindIf(!$isProduction, CacheInterface::class, ArrayCache::class);
```

---

## Binding Modes

| Mode | Constant | Behaviour |
| --- | --- | --- |
| **Singleton** | `BindingMode::Singleton` | Built once; same instance returned on every `get()` regardless of scope |
| **Scoped** | `BindingMode::Scoped` | Built once per active scope; discarded when the scope exits *(default)* |
| **Transient** | `BindingMode::Transient` | Fresh instance on every `get()` — never cached |

Lazy variants behave identically except that construction is deferred until `get()` is first called.
Scoped lazy instances are cached in `$lazyScopeCache` per scope, so they are built at most once per
scope lifetime.

---

## Resolving Services

### `get()`

```php
$container->get(string $abstract) : mixed
```

Resolves an abstract, returning a cached or freshly built instance. The lookup order is:

1. Alias resolution.
2. Scope stack walk — nearest scope instance wins.
3. Singleton cache.
4. Circular-dependency guard.
5. Binding registry — applies the configured lifetime strategy.
6. Factory registry.
7. Strict-mode check — throws `NotFoundException` if enabled and no binding exists.
8. Autowire — instantiates the class directly when not in strict mode.

```php
$logger = $container->get(LoggerInterface::class);
```

### `make()`

```php
$container->make(string $abstract, array $params = []) : mixed
```

Always constructs a fresh instance, bypassing every instance cache. Optional `$params` are
per-call constructor overrides that are applied only for this resolution and are rolled back
automatically afterwards.

```php
$command = $container->make(ImportCommand::class, ['chunkSize' => 500]);
```

### `has()`

```php
$container->has(string $abstract) : bool
```

Returns `true` if the container can produce an entry — bound, aliased, resolved instance, factory,
or an autoloadable class.

### `hasBinding()`

```php
$container->hasBinding(string $abstract) : bool
```

Returns `true` only when the abstract has an explicit registration (binding, instance, factory, or
alias). Unlike `has()`, bare autoloadable class names return `false`. Useful for strict-mode guards.

---

## Aliases

```php
$container->alias(string $alias, string $abstract) : static
```

Maps `$alias` to `$abstract` so that `get($alias)` is equivalent to `get($abstract)`. Resolved
transparently inside `get()`, `make()`, and `has()`.

```php
$container->alias('logger', LoggerInterface::class);
$logger = $container->get('logger');
```

---

## Factories

```php
$container->registerFactory(string $id, mixed $factory) : static
```

Registers a factory for a service identifier. Factories are consulted by `get()`, `make()`, and
the `Invoker` when resolving typed parameters.  A factory may be:

- A callable `fn (Container $c, string $class) : object`
- A `Factory` interface instance
- A class name whose `__invoke` accepts `(Container $c, string $class)`

A factory-backed identifier takes precedence over bare autowiring but yields to an explicit `bind()`.

```php
$container->registerFactory(PDO::class, fn (Container $c) =>
    new PDO($c->get('db.dsn'), $c->get('db.user'), $c->get('db.pass'))
);
```

---

## Removing and Rebinding

### `forget()`

```php
$container->forget(string $abstract) : static
```

Removes an abstract from all registries: bindings, instance caches, extenders, lifecycle callbacks,
tag maps, and the attribute cache. The abstract can be registered again from scratch afterwards.

### `rebind()`

```php
$container->rebind(string $abstract, string|callable $concrete, array $options = []) : static
```

Shortcut for `forget()` followed immediately by `bind()`. Useful for swapping an implementation
mid-lifecycle.

```php
$container->rebind(CacheInterface::class, NullCache::class);
```

---

## Decorators (Extend)

```php
$container->extend(string $abstract, callable $decorator) : static
```

Registers a decorator that receives `($instance, $container)` and must return the decorated
instance. Multiple decorators are applied in registration order on every fresh resolution.

```php
$container->extend(LoggerInterface::class, function (LoggerInterface $logger, Container $c) : LoggerInterface {
    return new TimestampLogger($logger);
});
```

---

## Scalar Parameters

### Global parameters

```php
$container->setParameter(string $param, mixed $value) : static
```

Sets a global fallback value for any constructor parameter of that name across all classes.

### Per-class parameters

```php
$container->addParameter(string $class, string $param, mixed $value) : static
```

Overrides a specific constructor parameter for a specific class.

```php
$container->addParameter(DatabaseConnection::class, 'timeout', 30);
```

---

## Injection Maps

Injection maps allow you to override how a specific property or constructor parameter is wired
without touching the class itself:

```php
// Constructor parameter override.
$container->addConstructorInjection(
    abstract: ServiceA::class,
    param: 'logger',
    service: JsonLogger::class,
    tag: null
);

// Property injection override.
$container->addPropertyInjection(
    abstract: ServiceB::class,
    property: 'mailer',
    service: SmtpMailer::class,
    tag: null
);
```

---

## Lazy Proxy

```php
$container->createProxy(string $abstract) : object
```

Returns a proxy that defers instantiation until the first method call. On PHP 8.4+, a native
`ReflectionClass::newLazyProxy()` instance is returned for non-final, non-abstract classes,
guaranteeing full type compatibility. On earlier PHP versions, or when the concrete is final or
abstract, a `LazyProxy` magic-method fallback is returned (cannot satisfy strict type hints at
injection points).

```php
$proxy = $container->createProxy(HeavyService::class);
// HeavyService is not yet instantiated at this point.
$result = $proxy->doSomething();
// HeavyService is now built and the call forwarded.
```

---

## Container Forking

```php
$child = $container->fork() : static
```

Creates a child container that inherits all bindings, aliases, contextual rules, tags, factories,
parameters, extenders, injection maps, and the attribute cache from the parent. The child starts
with an empty instance store — parent singletons are not shared into the child and vice versa.
Lifecycle callbacks and dependency graphs are not inherited.

Overriding a binding on the child has no effect on the parent.

```php
$child = $container->fork();
$child->bind(LoggerInterface::class, NullLogger::class);

$container->get(LoggerInterface::class); // FileLogger (parent unchanged)
$child->get(LoggerInterface::class);     // NullLogger
```

---

## Strict Mode

```php
$container->setStrict(bool $value) : static
```

When enabled, `get()` and `make()` throw `NotFoundException` for any abstract with no explicit
binding registered, disabling implicit autowiring.

```php
$container->setStrict(true);
$container->get(UnregisteredService::class); // throws NotFoundException
```

---

## Property Injection Options

```php
// Inject protected properties in addition to public ones.
$container->setInjectProtected(bool $value) : static

// Only inject properties carrying the #[Inject] attribute.
$container->setInjectableOnlyWithAttributes(bool $value) : static
```

---

## Cache Warmup

```php
$container->warmup() : static
```

Pre-populates the reflection cache and runs attribute scanning for all currently registered
bindings. Call once during application boot to eliminate per-request reflection overhead on
the first resolution of each service.

```php
$container->loadConfig('config/services.json');
$container->warmup();
```

---

## Cache Export and Import

See [Configuration](Configuration.md) for a full description of serialised cache round-trips.

```php
$container->exportCache('/tmp/container.cache.php');
$container->loadCache('/tmp/container.cache.php');
```

---

## Calling Callables

```php
$container->call(callable $callable, array $params = []) : mixed
```

Invokes a callable, resolving all typed parameters from the container. Named `$params` override
specific arguments. Contextual bindings defined for the callable's declaring class are honoured.

```php
$result = $container->call([$orderService, 'process'], ['orderId' => 42]);
```

For more powerful invocation with factory injection, scope control, and external object passing,
use the `Invoker` — see [Invoker](Invoker.md).
