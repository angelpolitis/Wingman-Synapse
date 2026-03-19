# Scopes

Scopes partition instance lifetimes inside the container. Every container starts with a single,
permanent **global** scope. Additional scopes can be pushed onto a scope stack, become the active
scope for the duration they are on the stack, and are discarded (along with all their cached
instances) when they are popped.

---

## The Global Scope

The global scope is always present and can never be exited:

```php
Container::GLOBAL_SCOPE // "global"
```

Calling `exitScope()` when only the global scope is on the stack throws a `RuntimeException`.

---

## Registering a Scope

Before entering a scope it must be registered so the container knows to initialise its instance cache:

```php
$container->registerScope('request');
$container->registerScope('session');
```

Registering a scope that already exists is a no-op.

---

## Entering and Exiting a Scope

```php
$container->enterScope(string $scopeName) : static
$container->exitScope() : static
```

`enterScope()` pushes a scope name onto the scope stack and emits `Signal::SCOPE_ENTERED`.
`exitScope()` pops the current scope, clears all scope-partitioned caches for it, and emits
`Signal::SCOPE_EXITED`.

```php
$container->registerScope('request');
$container->enterScope('request');

try {
    // Services resolved here with BindingMode::Scoped are cached for the 'request' scope.
    $repo = $container->get(UserRepository::class);
    // $repo is the same instance for every get() within this enterScope/exitScope block.
} finally {
    $container->exitScope();
    // All UserRepository and other scoped instances for 'request' are now discarded.
}
```

---

## Scope Stack

Scopes nest arbitrarily. The innermost scope on the stack is always the active scope:

```php
$container->enterScope('session');
  // Active scope: session
  $container->enterScope('request');
    // Active scope: request
    $container->get(SomeService::class); // cached under 'request'
  $container->exitScope(); // exits 'request'
  // Active scope: session
$container->exitScope(); // exits 'session'
// Active scope: global
```

`get()` walks the stack from the innermost outward when looking for a cached scoped instance,
so a service resolved in an outer scope is visible inside inner scopes of the same request.

---

## Scoped Bindings

A binding's `scope` option pins it to a specific scope name. Instances for that binding are cached
under the named scope regardless of which scope is currently active at resolution time:

```php
$container->bind(RequestLogger::class, RequestLogger::class, [
    'mode' => BindingMode::Scoped,
    'scope' => 'request',
]);
```

Omitting `scope` (or passing `null`) defaults to the scope that was active when `bind()` was called.

---

## Lazy Scoped Bindings

A lazy scoped binding defers construction until the first `get()` within its scoped lifetime:

```php
$container->bindLazy(RequestLogger::class, RequestLogger::class, [
    'scope' => 'request',
]);

// Or equivalently:
$container->bind(RequestLogger::class, RequestLogger::class, [
    'mode' => BindingMode::Scoped,
    'lazy' => true,
    'scope' => 'request',
]);
```

The instance is stored in `$lazyScopeCache` per scope and is discarded along with the scope.
Both `get()` and `getByTag()` honour this cache, so only one instance is ever built per scope.

---

## Scope Events (Corvus)

When Corvus is installed, `enterScope()` and `exitScope()` emit the following signals:

| Signal | Payload |
| --- | --- |
| `Signal::SCOPE_ENTERED` | `scope` — the scope name just entered |
| `Signal::SCOPE_EXITED` | `scope` — the scope name just exited |

See [Signals](Signals.md) for details.

---

## Scoped Dependency Graph

Each scope accumulates its own dependency graph as services are resolved:

```php
$container->enterScope('request');
$container->get(OrderService::class);

$graph = $container->getScopedDependencyGraph('request');
// ['OrderService' => ['Repository', 'Logger'], ...]

$container->exitScope();
```

---

## Invoker Scope Integration

The `Invoker` can enter and exit a named scope around a callable invocation automatically —
see [Invoker](Invoker.md).
