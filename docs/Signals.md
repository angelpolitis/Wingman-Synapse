# Corvus Signals

When `wingman/corvus` is installed, the container emits structured signals at each key lifecycle
event through the Corvus event bus. When Corvus is absent, all emission calls are no-ops — the
container operates identically without any hard dependency.

---

## Signal Enum

`Wingman\Synapse\Enums\Signal` is a backed `string` enum. Each case carries a dot-notation
identifier that Corvus listeners subscribe to.

| Case | Value | Emitted when | Payload |
| --- | --- | --- | --- |
| `Signal::BOUND` | `synapse.bound` | A binding is registered via `bind()` | `id` — abstract identifier |
| `Signal::RESOLVING` | `synapse.resolving` | A service is constructed, before extenders | `id` — abstract identifier |
| `Signal::RESOLVED` | `synapse.resolved` | A service is fully resolved and decorated | `id` — abstract identifier |
| `Signal::SCOPE_ENTERED` | `synapse.scope.entered` | A scope is pushed onto the stack | `scope` — scope name |
| `Signal::SCOPE_EXITED` | `synapse.scope.exited` | A scope is popped from the stack | `scope` — scope name |

---

## Subscribing to Signals

When Corvus is present, subscribe using the standard Corvus `Listener` API:

```php
use Wingman\Corvus\Listener;
use Wingman\Synapse\Enums\Signal;

Listener::create()
    ->when(Signal::RESOLVED)
    ->do(function (string $id): void {
        error_log("Resolved: $id");
    });

Listener::create()
    ->when(Signal::SCOPE_ENTERED)
    ->do(function (string $scope): void {
        error_log("Scope entered: $scope");
    });
```

Pattern matching also works since signals use dot-notation:

```php
Listener::create()
    ->when('synapse.*')
    ->do(function (): void {
        // Fires for every container signal.
    });
```

---

## Corvus Bridge

The `Bridge\Corvus\Emitter` class in `src/Bridge/Corvus/Emitter.php` handles the conditional
integration:

- If `Wingman\Corvus\Emitter` exists in the autoloader, the bridge aliases it into the
  `Wingman\Synapse\Bridge\Corvus` namespace.
- If Corvus is absent, the bridge defines a no-op stub class with the same public interface
  (silent `emit()`, `with()`, `if()`, etc.) so `Container` compiles and runs without modification.

---

## Signal Payload

Each signal is emitted with named keyword arguments passed to `->with()`:

| Signal | Payload key | Type |
| --- | --- | --- |
| `BOUND` | `id` | `string` — abstract identifier |
| `RESOLVING` | `id` | `string` — abstract identifier |
| `RESOLVED` | `id` | `string` — abstract identifier |
| `SCOPE_ENTERED` | `scope` | `string` — scope name |
| `SCOPE_EXITED` | `scope` | `string` — scope name |

---

## Installing Corvus

```bash
composer require wingman/corvus
```

No configuration is needed. The bridge detects Corvus automatically at runtime and all signals
begin flowing through the event bus without any code changes.
