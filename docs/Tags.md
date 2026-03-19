# Tags

Tags let you attach one or more named labels to a service and retrieve all services sharing a
label in a single call. Priority ordering controls the position of each service in the returned
array.

---

## Registering Tags

### `tag()`

```php
$container->tag(string $tagName, string $abstract, int $priority = 0) : static
```

Associates `$abstract` with `$tagName`. If the same abstract is tagged more than once with the
same tag name the entry is overwritten (last write wins).

```php
$container->bind(CsvExporter::class, CsvExporter::class);
$container->bind(PdfExporter::class, PdfExporter::class);

$container->tag('exporter', CsvExporter::class, priority: 10);
$container->tag('exporter', PdfExporter::class, priority: 5);
```

Higher priority values appear earlier in the array returned by `getByTag()`.

### Tagging at Bind Time

Tags can be embedded directly in a `bind()` options array, saving a separate `tag()` call:

```php
$container->bind(CsvExporter::class, CsvExporter::class, [
    'tags' => ['exporter' => 10]
]);
```

When the value is non-numeric the key is treated as the tag name with priority 0:

```php
$container->bind(CsvExporter::class, CsvExporter::class, [
    'tags' => ['exporter']
]);
```

---

## Resolving by Tag

```php
$container->getByTag (
    array|string $tags,
    ?callable $filter = null,
    ?string $scope = null
) : array
```

Returns all resolved instances registered under the given tag(s), sorted by descending priority.

```php
$exporters = $container->getByTag('exporter');
// [CsvExporter (priority 10), PdfExporter (priority 5)]
```

### Multiple Tags

Pass an array to merge services from several tags, de-duplicated by abstract:

```php
$handlers = $container->getByTag(['http.handler', 'cli.handler']);
```

### Filtering

Pass a callable that receives each resolved instance and returns `true` to keep it:

```php
$asyncExporters = $container->getByTag('exporter', fn ($e) => $e instanceof AsyncExporterInterface);
```

### Scope Override

The `$scope` argument changes which scope-specific tag registry is consulted:

```php
$requestTags = $container->getByTag('middleware', scope: 'request');
```

---

## Scope-Specific Tags

Tags registered while a named scope is active are stored in the scope-specific tag registry in
addition to the global one. When `getByTag()` is called, scope-specific entries take precedence
over global ones with the same abstract.

```php
$container->enterScope('request');
$container->tag('middleware', AuthMiddleware::class, priority: 100);
$container->exitScope();
```

---

## Binding Modes and Tag Resolution

`getByTag()` resolves each tagged abstract respecting its binding mode:

| Binding | Behaviour in `getByTag()` |
| --- | --- |
| Singleton lazy | Cached in `$instances`; built at most once for the container lifetime |
| Scoped lazy | Cached in `$lazyScopeCache` per scope; built at most once per scope lifetime |
| All other modes | Delegates to `get($abstract)` — standard scoped/transient/singleton semantics apply |

---

## Injecting Tagged Services

Use `#[Inject(tag: 'tagName')]` on an `array`-typed property or parameter to have the container
inject all services matching the tag automatically:

```php
class ExportManager {
    #[Inject(tag: 'exporter')]
    public array $exporters;
}
```

See [Attributes](Attributes.md) for the full `#[Inject]` reference.

---

## JSON Configuration

Tags can be declared in the `"tags"` section of a JSON config file:

```json
{
    "tags": {
        "exporter": ["App\\Export\\CsvExporter", "App\\Export\\PdfExporter"],
        "middleware": {
            "App\\Http\\AuthMiddleware": 100,
            "App\\Http\\CorsMiddleware": 50
        }
    }
}
```

See [Configuration](Configuration.md) for the full JSON format reference.
