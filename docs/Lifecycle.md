# Lifecycle Hooks

The container exposes several hook points that let you observe and modify resolution at each
stage of the lifecycle. All hooks are registered on the container instance and fire during
every matching resolution unless noted otherwise.

---

## Before Resolution

```php
$container->onBeforeResolving(callable $callback) : static
```

The callback fires immediately before `get()` or `make()` begins resolving an abstract — before
any cache lookup, circular-dependency check, or construction.

Signature: `function (string $abstract, Container $container) : void`

```php
$container->onBeforeResolving(function (string $abstract, Container $c) : void {
    error_log("Resolving: $abstract");
});
```

> `ProviderManager` uses `onBeforeResolving()` internally to trigger deferred provider boot on
> first access of any provided abstract.

---

## After Construction (Before Extenders)

```php
$container->onResolving(string $abstract, callable $callback) : static
```

The callback fires immediately after a service is constructed, before any registered decorators
(`extend()`) are applied.

Pass `'*'` as `$abstract` to hook every resolved service.

Signature: `function (mixed $instance, Container $container) : void`

```php
$container->onResolving(LoggerInterface::class, function (LoggerInterface $logger, Container $c) : void {
    $logger->setContext(['app' => 'myapp']);
});

// Wildcard — fires for every resolution.
$container->onResolving('*', function (mixed $instance, Container $c) : void {
    if ($instance instanceof Configurable) {
        $instance->configure($c->get(Config::class));
    }
});
```

---

## After Full Resolution (After Extenders)

```php
$container->onResolved(string $abstract, callable $callback) : static
```

The callback fires after the instance has been passed through all registered decorators.

Pass `'*'` as `$abstract` to hook every resolved service.

Signature: `function (mixed $instance, Container $container) : void`

```php
$container->onResolved(UserRepository::class, function (UserRepository $repo, Container $c) : void {
    $repo->setEventBus($c->get(EventBus::class));
});
```

---

## On Bind

```php
$container->onBind(string $abstract, callable $callback) : static
```

The callback fires whenever `bind()` (or any of its shortcut wrappers) registers or re-registers
the given abstract. Pass `'*'` as `$abstract` to observe every bind call.

Signature: `function (string $abstract, string|callable $concrete, Container $container) : void`

```php
$container->onBind('*', function (string $abstract, $concrete, Container $c) : void {
    error_log("Bound: $abstract");
});
```

---

## Decorators (Extend)

```php
$container->extend(string $abstract, callable $decorator) : static
```

Registers a decorator callable that transforms each freshly resolved instance of `$abstract`.
Multiple decorators are run in registration order and each receives the output of the previous one.

Signature: `function (mixed $instance, Container $container) : mixed`

```php
$container->extend(LoggerInterface::class, function (LoggerInterface $logger, Container $c) : LoggerInterface {
    return new PrefixLogger($logger, '[APP]');
});

$container->extend(LoggerInterface::class, function (LoggerInterface $logger, Container $c) : LoggerInterface {
    return new TimestampLogger($logger);
});
```

The decoration pipeline is:

```
resolve (construct) → resolving callbacks → extender 1 → extender 2 → … → resolved callbacks
```

---

## Corvus Signals

When Corvus is installed, the container emits structured signals at each key lifecycle event.
These signals are received by any Corvus listener subscribed to the matching pattern.

| Signal | Emitted when | Payload |
| --- | --- | --- |
| `Signal::BOUND` | A binding is registered via `bind()` | `id` — abstract identifier |
| `Signal::RESOLVING` | A service has been constructed, before extenders | `id` — abstract identifier |
| `Signal::RESOLVED` | A service has been fully resolved and decorated | `id` — abstract identifier |
| `Signal::SCOPE_ENTERED` | A scope is pushed onto the stack | `scope` — scope name |
| `Signal::SCOPE_EXITED` | A scope is popped from the stack | `scope` — scope name |

See [Signals](Signals.md) for the full signal reference.

---

## Execution Order Summary

For a typical `get()` call on an unresolved scoped service:

1. `onBeforeResolving` callbacks fire.
2. Service is constructed (`resolve()`).
3. `resolving` callbacks fire (per-abstract then wildcard).
4. `Signal::RESOLVING` emitted.
5. Extenders applied in registration order.
6. `resolved` callbacks fire (per-abstract then wildcard).
7. `Signal::RESOLVED` emitted.
8. Instance stored in the appropriate cache.
