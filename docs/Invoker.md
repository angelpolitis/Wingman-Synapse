# Invoker

`Wingman\Synapse\Invoker` is a fluent wrapper around `Container::call()` that adds factory
injection, external object pre-loading, per-invocation scope management, and secondary parameter
maps.

---

## Creating an Invoker

```php
$invoker = $container->createInvoker();
```

All builder methods return `static`, so they can be chained before calling `call()`.

---

## Calling a Callable

```php
$invoker->call(object|array|string|callable $context, ?string $method = null) : mixed
```

Resolves all unspecified typed parameters from the container and invokes the callable. The
`$context` argument supports several forms:

| Form | Example |
| --- | --- |
| Closure / global function name | `fn (Logger $l) => $l->info('hi')` |
| `[object, 'method']` | `[$service, 'handle']` |
| `[ClassName::class, 'staticMethod']` | `[Formatter::class, 'format']` |
| Class name + separate method | `call(OrderService::class, 'process')` |
| Object + separate method | `call($order, 'validate')` |

```php
$result = $container->createInvoker()
    ->call(fn (Logger $logger, Config $cfg) => $logger->info($cfg->get('name')));
```

---

## Primary Parameter Map — `useParams()`

```php
$invoker->useParams(array $params): static
```

Sets the primary named parameter map. Parameters in this map are matched by name before any
type-based container resolution is attempted.

```php
$invoker->useParams(['orderId' => 42])->call([$handler, 'handle']);
```

---

## Secondary Parameter Map — `useExtraParams()`

```php
$invoker->useExtraParams(array $params) : static
```

Merges extra named parameters that are checked after the primary map but before container
resolution. The array must be associative; positional arrays are rejected with an exception.

```php
$invoker->useExtraParams(['locale' => 'en_GB'])->call($formatter);
```

---

## External Objects — `useObjects()`

```php
$invoker->useObjects(object ...$objects): static
```

Pre-supplies typed objects that are matched by class name during parameter resolution. Useful for
passing objects that are not registered in the container.

```php
$invoker->useObjects($request, $response)->call([$controller, 'index']);
```

---

## Factory Override — `useFactory()`

```php
$invoker->useFactory(Factory|callable|string|null $factory) : static
```

Sets a per-invocation factory used when building typed object parameters. The factory receives
`(Container $container, string $className)` and must return the object. If `null`, any factory
registered in the container via `registerFactory()` is used instead.

```php
$invoker->useFactory(fn (Container $c, string $class) => $c->make($class, ['debug' => true]))
        ->call([$command, 'execute']);
```

---

## Scope Control — `useScope()`

```php
$invoker->useScope(?string $scope) : static
```

Wraps the invocation in `enterScope()` / `exitScope()`. When the given scope is already the active
scope, no scope transitions happen. Passing `null` clears any previously set scope.

```php
$invoker->useScope('request')
        ->call(fn (RequestRepository $repo) => $repo->findAll());
```

---

## `#[Inject]` on Parameters

Inside the callable the invoker honours `#[Inject]` on parameters just as `Container::resolve()`
does — allowing service or tag overrides without any explicit parameter map:

```php
$invoker->call(function (
    #[Inject(service: CsvExporter::class)]
    ExporterInterface $exporter,
) : void {
    $exporter->export($data);
});
```

---

## Resolution Priority

For each parameter the invoker resolves in this order:

1. Primary `$params` map (by name).
2. Extra `$params` map (by name).
3. `$objects` map (by class name).
4. `#[Inject]` attribute → tag or explicit service.
5. Per-invocation or container-registered factory.
6. Container `get()` (type-based).
7. `object`-typed parameter with at least one loaded object → first object.
8. Parameter default value.
9. `RuntimeException` — parameter cannot be resolved.
