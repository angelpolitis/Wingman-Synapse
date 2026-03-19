# Contextual Bindings

Contextual bindings let you inject a different concrete type depending on which class is being
constructed. This is useful when multiple components depend on the same interface but require
distinct implementations.

---

## Fluent API

```php
$container->forConsumer(string $consumer) : ContextualBindingBuilder
```

Returns a `ContextualBindingBuilder` for the named consumer class.  Chain `needs()` and `give()`
to complete the declaration:

```php
$container
    ->forConsumer(OrderService::class)
    ->needs(LoggerInterface::class)
    ->give(OrderLogger::class);

$container
    ->forConsumer(PaymentService::class)
    ->needs(LoggerInterface::class)
    ->give(PaymentLogger::class);
```

`OrderService` will receive an `OrderLogger`; `PaymentService` will receive a `PaymentLogger`.
All other consumers continue receiving whatever the default binding for `LoggerInterface::class`
provides.

---

## Direct Registration

```php
$container->setContextualBinding(
    string $consumer,
    string $needs,
    string|callable|object $concrete
) : static
```

Registers a contextual override without the fluent builder. The `$concrete` may be a class name,
a factory callable, or a pre-built object.

---

## Factory and Instance Overrides

```php
// Inline factory
$container
    ->forConsumer(ReportExporter::class)
    ->needs(FormatterInterface::class)
    ->give(fn (Container $c) => new CsvFormatter($c->get('config.csv')));

// Pre-built object
$container
    ->forConsumer(DebugController::class)
    ->needs(LoggerInterface::class)
    ->give(new EchoLogger());
```

---

## Scope-Locked Contextual Bindings

When registering directly via `setContextualBinding()` (or the `#[Context]` attribute), the
concrete can be wrapped in an array to lock the override to a specific scope:

```php
// Via setContextualBinding passing an array (internal format used by the scanner):
$container->contextual[ConsumerClass::class][SomeInterface::class] = [
    'concrete' => SpecialImpl::class,
    'scope' => 'request',
];
```

When the active scope does not match the declared scope, the override is ignored and the default
binding is used instead.

---

## Attribute Declaration

The `#[Context]` attribute on a concrete class is equivalent to calling `setContextualBinding()`.

```php
use Wingman\Synapse\Attributes\Context;

#[Context(consumer: OrderService::class, needs: LoggerInterface::class)]
class OrderLogger implements LoggerInterface {
    // ...
}
```

An optional `scope` parameter restricts the override to a named scope:

```php
#[Context(consumer: OrderService::class, needs: LoggerInterface::class, scope: 'request')]
class OrderLogger implements LoggerInterface { ... }
```

The attribute is scanned automatically on first resolution of the concrete — no manual registration
is needed. See [Attributes](Attributes.md) for the full attribute reference.

---

## Interface and Trait Alternatives

For cases where attributes are not practical, implement `Interfaces\Context` or use `Traits\Context` on the concrete class.  Both require the class to expose `getConsumer()` and `getNeeds()` returning the respective FQCNs.

See [Attributes](Attributes.md#interface-and-trait-alternatives) for examples.

---

## How Resolution Works

During `resolve()`, for every typed constructor parameter the container checks:

1. `#[Inject]` attribute override (explicit service or tag).
2. `$constructorInjectionMap` entry (from JSON config or `addConstructorInjection()`).
3. Contextual binding keyed by `[consumer][dependency]`.
4. Default binding or autowiring fallback.

The same order applies inside `Container::call()` for callable parameters.
