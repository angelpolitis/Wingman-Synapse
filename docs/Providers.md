# Service Providers

Service providers organise container registrations into discrete, reusable units with a
structured two-phase lifecycle. The `ProviderManager` orchestrates ordering, deferred boot,
and lifecycle event signalling.

---

## The Provider Base Class

Extend `Wingman\Synapse\Provider` to create a provider:

```php
use Wingman\Synapse\Provider;

class DatabaseProvider extends Provider {
    public function register () : void {
        $this->container->bindSingleton(Connection::class, MySqlConnection::class);
        $this->container->bindSingleton(QueryBuilder::class, SqlQueryBuilder::class);
    }

    public function boot () : void {
        $this->onResolved(Connection::class, function (Connection $conn) : void {
            $conn->connect();
        });
    }
}
```

### Phase 1 — `register()`

`register()` is called first for all non-deferred providers. Declare bindings, aliases, factories,
and injection rules here. At this point, other providers may not yet have run `register()`, so
avoid resolving services.

### Phase 2 — `boot()`

`boot()` runs after every non-deferred provider has registered. It is safe to resolve services and
call `onResolved()` here.

---

## Provider Fields

| Field | Type | Default | Description |
| --- | --- | --- | --- |
| `$deferred` | `bool` | `false` | Defer this provider until a provided abstract is first requested |
| `$provides` | `string[]` | `[]` | Abstract identifiers this provider will register (only read when `$deferred = true`) |
| `$dependsOn` | `class-string[]` | `[]` | Provider classes that must run before this one — throws if any are absent |
| `$softDependsOn` | `class-string[]` | `[]` | Provider classes that should run before this one — silently ignored when absent |
| `$priority` | `int` | `0` | Higher values run earlier when no dependency order applies |

---

## Provider Helpers

```php
protected function onResolved (string $abstract, callable $callback) : void
```

Convenience wrapper around `Container::onResolved()`.

```php
protected function mergeConfig (array $defaults, array $overrides) : array
```

Recursively merges `$overrides` into `$defaults`, useful for configuration providers that ship
sensible defaults the application can partially override.

---

## ProviderManager

`ProviderManager` handles the full lifecycle:

```php
use Wingman\Synapse\ProviderManager;

$manager = new ProviderManager($container);
$manager->addProvider(DatabaseProvider::class);
$manager->addProvider(MailProvider::class);
$manager->addProvider(CacheProvider::class);
$manager->boot();
```

### `addProvider()` / `addProviders()`

Accepts a provider class name or a pre-instantiated `ProviderInterface` instance:

```php
$manager->addProvider(DatabaseProvider::class);
$manager->addProvider(new CacheProvider($container));
$manager->addProviders([DatabaseProvider::class, MailProvider::class]);
```

### `boot()`

Resolves provider execution order and runs the lifecycle:

1. Computes topological order: `$dependsOn` edges first, then `$priority` descending, then class
   name ascending.
2. Calls `register()` on every non-deferred provider in order.
3. Calls `boot()` on every non-deferred provider in order.
4. Indexes deferred providers by their provided abstracts.

Each `register()` and `boot()` is called at most once per provider instance.

---

## Provider Dependencies

Declare `$dependsOn` to guarantee ordering:

```php
class MailProvider extends Provider {
    protected array $dependsOn = [DatabaseProvider::class];

    public function register () : void {
        // DatabaseProvider::register() has already run.
        $this->container->bindSingleton(Mailer::class, SmtpMailer::class);
    }
}
```

`ProviderManager` throws a `RuntimeException` if a dependency is missing or if a circular
dependency is detected.

---

## Optional Provider Dependencies

Use `$softDependsOn` when ordering should be respected only if the listed provider is actually
registered. This is the correct tool for cross-package bridges where the secondary package is an
optional integration.

```php
// ORM bridge — runs after DatabaseProvider when Database is installed, ignored otherwise.
class OrmProvider extends Provider {
    protected array $softDependsOn = [DatabaseProvider::class];

    public function register () : void {
        $this->container->bindSingleton(Repository::class, EloquentRepository::class);
    }
}
```

For wiring that cannot be expressed inside the provider itself — for example, when both providers
belong to third-party packages or when ordering is decided by a framework bootstrap layer — use
`addDependency()` on the manager instead:

```php
$manager->addProvider(DatabaseProvider::class);
$manager->addProvider(OrmProvider::class);

// Declare the edge from the outside. Silently ignored if either provider is absent.
$manager->addDependency(OrmProvider::class, DatabaseProvider::class);

$manager->boot();
```

Both approaches produce the same topological edge. If the named dependency is not registered when
`boot()` runs, the constraint is discarded and ordering falls back to priority and class name.

---

## Deferred Providers

A deferred provider is not booted until the first time the container is asked to resolve one of
its provided abstracts.

```php
class PdfProvider extends Provider {
    protected bool $deferred = true;
    protected array $provides = [PdfRenderer::class, PdfTemplate::class];

    public function register() : void {
        $this->container->bindSingleton(PdfRenderer::class, DompdfRenderer::class);
        $this->container->bindSingleton(PdfTemplate::class, BladeTemplate::class);
    }
}
```

`ProviderManager` hooks into `Container::onBeforeResolving()` — when `get(PdfRenderer::class)` is
first called, `register()` and then `boot()` run transparently before the resolution proceeds.

> Only the abstracts listed in `$provides` trigger deferred boot. Using an unlisted abstract does
> not trigger the provider.

---

## Lifecycle Events

`ProviderManager` exposes five listener registration methods. Each fires at the named point in
the provider lifecycle:

```php
$manager->onRegistering(function (string $class, ProviderInterface $provider) : void {
    // About to call register()
});

$manager->onRegistered(function (string $class, ProviderInterface $provider) : void {
    // register() completed successfully
});

$manager->onBooting(function (string $class, ProviderInterface $provider) : void {
    // About to call boot()
});

$manager->onBooted(function (string $class, ProviderInterface $provider) : void {
    // boot() completed successfully
});

$manager->onFailed(function (string $class, ProviderInterface $provider, Throwable $error) : void {
    // register() or boot() threw an exception
});
```

When a provider throws during `register()` or `boot()`, the `onFailed` listeners receive the
original `Throwable`, and the manager re-throws a `RuntimeException` wrapping it.
