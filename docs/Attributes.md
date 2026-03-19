# Attributes

Synapse provides four PHP attributes that let classes declare their own registration rules
without any manual `bind()` calls. The container scans attributes the first time a class is
resolved and caches the result so scanning only ever happens once per concrete.

For each attribute, an identical interface and trait alternative is available for situations
where attributes are not practical (e.g. generated classes).

---

## `#[Service]` vs `#[Bind]` — When to Use Which

The single most important rule:

> **Use `#[Service]` when the class IS the identifier. Use `#[Bind]` when the class implements an interface.**

| Scenario | Attribute | Result |
|---|---|---|
| No interface; resolve by class name | `#[Service]` | `get(MyClass::class)` returns a `MyClass` instance |
| Implements an interface | `#[Bind]` | `get(TheInterface::class)` returns the concrete |

If you use `#[Service]` on a class that implements an interface, callers must still ask for the
concrete class name — the interface is not automatically wired. If you use `#[Bind]`, the class
is wired to the interface and callers ask for the interface.

---

## `#[Service]`

```php
#[Attribute(Attribute::TARGET_CLASS)]
class Service {
    public function __construct (
        public bool $singleton = true,
        public ?string $scope = null
    ) {}
}
```

Marks a class as an auto-registered service that **binds to itself**. The class name is both
the abstract identifier and the concrete. By default the class is registered as a singleton.
Pass `singleton: false` for a scoped binding.

Use `#[Service]` for:
- Utility and orchestration classes that are requested directly (e.g. `EventDispatcher`,
  `QueryBuilder`, `CacheManager`).
- Classes that do not implement any interface worth injecting by.
- Classes that are resolved by their own type hint in other constructors.

```php
use Wingman\Synapse\Attributes\Service;

// get(FileLogger::class) returns the same FileLogger instance for the container lifetime.
#[Service]
class FileLogger {
    public function __construct (private string $path) {}
}

// get(RequestContext::class) returns one instance per 'request' scope.
#[Service(singleton: false, scope: 'request')]
class RequestContext {
    public string $userId = '';
}
```

---

## `#[Bind]`

```php
#[Attribute(Attribute::TARGET_CLASS)]
class Bind {
    public function __construct (
        public string $abstract,
        public bool $singleton = false,
        public ?string $scope = null,
        public bool $lazy = false
    ) {}
}
```

Associates a class with an **interface or abstract base class**. The binding defaults to scoped
unless `singleton: true` or `lazy: true` is specified.

Use `#[Bind]` for:
- Any class that implements a shared interface and should be the default concrete for that interface
  (e.g. `FileLogger` as the default `LoggerInterface`).
- Swappable dependencies — the rest of the codebase depends on the interface, not the concrete.

```php
use Wingman\Synapse\Attributes\Bind;

// get(LoggerInterface::class) returns the same FileLogger for the container lifetime.
#[Bind(abstract: LoggerInterface::class, singleton: true)]
class FileLogger implements LoggerInterface {
    public function info (string $message): void { /* ... */ }
}

// get(CacheInterface::class) builds a RedisCache once per 'request' scope, on first access.
#[Bind(abstract: CacheInterface::class, lazy: true, scope: 'request')]
class RedisCache implements CacheInterface {
    public function get (string $key): mixed { /* ... */ }
}
```

### Can I use both on the same class?

Yes, but it is rarely necessary. A class instrumented with both `#[Service]` and `#[Bind]` will
be resolvable both by its own class name and by the abstract it declares — two independent
bindings are registered. Prefer `#[Bind]` alone when an interface exists.

---

## `#[Context]`

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Context {
    public function __construct (
        public string $consumer,
        public string $needs,
        public ?string $scope = null
    ) {}
}
```

Declares that a specific consumer should receive this class when it requests the given abstract.
Equivalent to calling `Container::setContextualBinding()`.

`#[Context]` is **repeatable** — you can stack multiple declarations on the same class when it
serves as the contextual override for more than one consumer.

```php
use Wingman\Synapse\Attributes\Context;

// AuditLogger is the override for two different consumers, both requesting LoggerInterface.
#[Context(consumer: OrderService::class, needs: LoggerInterface::class)]
#[Context(consumer: PaymentService::class, needs: LoggerInterface::class)]
class AuditLogger implements LoggerInterface {
    // OrderService AND PaymentService receive AuditLogger; all other consumers get the default.
}
```

Each `#[Context]` instance is registered independently in the container's contextual map under
its own `consumer → needs` key, so the number of declarations on a single class is unlimited.

Add `scope` to lock an override to a specific scope — outside that scope the default binding
for the abstract is used instead:

```php
#[Context(consumer: OrderService::class, needs: LoggerInterface::class)]
#[Context(consumer: PaymentService::class, needs: LoggerInterface::class, scope: 'request')]
class AuditLogger implements LoggerInterface {
    // OrderService always gets AuditLogger.
    // PaymentService only gets AuditLogger inside the 'request' scope.
}
```

---

## `#[Inject]`

```php
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Inject {
    public function __construct (
        public ?string $service = null,
        public ?string $tag = null
    ) {}
}
```

Applied to a constructor parameter or property to override what the container injects.

### Service override

```php
class ReportService {
    public function __construct (
        #[Inject(service: JsonLogger::class)]
        private LoggerInterface $logger
    ) {}
}
```

### Tag injection

When applied to an `array`-typed property or parameter, injects all services registered under the
given tag:

```php
class ExportManager {
    #[Inject(tag: 'exporter')]
    public array $exporters;
    // All services tagged 'exporter' are injected, sorted by priority.
}
```

### Bare `#[Inject]`

On a property, `#[Inject]` with no arguments forces standard type-based injection even when
`setInjectableOnlyWithAttributes(true)` is enabled. On a constructor parameter it does the same.

---

## Interface and Trait Alternatives

All three class-level attributes have corresponding interface and trait equivalents that are
scanned alongside attributes during `registerAttributes()`.

### `Interfaces\Service` / `Traits\Service`

Implement `Wingman\Synapse\Interfaces\Service` or use `Wingman\Synapse\Traits\Service` to declare
a scoped or singleton self-binding without attributes.

```php
use Wingman\Synapse\Interfaces\Service as ServiceInterface;

class FileLogger implements ServiceInterface {
    // Registered as a singleton (isSingleton() returns true by convention in the interface).
}
```

### `Interfaces\Bind` / `Traits\Bind`

Implement or use these to declare which abstract a class binds to. The class must implement
`getAbstract(): string` returning the target FQCN as a compile-time constant.

```php
use Wingman\Synapse\Interfaces\Bind as BindInterface;

class FileLogger implements BindInterface {
    public function getAbstract () : string {
        return LoggerInterface::class;
    }
}
```

### `Interfaces\Context` / `Traits\Context`

For a contextual binding declaration the class must implement `getConsumer(): string` and
`getNeeds(): string`, both returning constant FQCNs.

```php
use Wingman\Synapse\Interfaces\Context as ContextInterface;

class OrderLogger implements ContextInterface {
    public function getConsumer () : string {
        return OrderService::class;
    }
    public function getNeeds () : string {
        return LoggerInterface::class;
    }
}
```

---

## Scan Mechanics

Scanning is triggered inside `resolve()` on first construction. The scanner checks all three
registration paths in order: **attributes → interfaces → traits**. Results are stored in
`$attributeCache` keyed by FQCN so subsequent resolutions skip scanning entirely.

Calling `warmup()` forces scanning for all currently registered bindings at boot time, eliminating
the per-request overhead for the first resolution of each service.
