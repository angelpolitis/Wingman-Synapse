# Configuration

Synapse supports two complementary configuration mechanisms: a declarative JSON file format
(parsed by `ConfigLoader`) and a serialised PHP cache file for zero-overhead subsequent boots.

---

## JSON Configuration File

```php
$container->loadConfig(string $jsonFile) : static
```

Parses the JSON file and maps each section to the appropriate container API calls.

### Full Format Reference

```json
{
    "scopes": [
        "request",
        "session"
    ],

    "services": {
        "App\\Logger\\FileLogger": {
            "concrete": "App\\Logger\\FileLogger",
            "mode": "singleton",
            "lazy": false,
            "scope": null,
            "tags": {
                "logger": 10
            },
            "constructorInjection": {
                "path": {
                    "service": null,
                    "tag": null
                }
            },
            "propertyInjection": {
                "formatter": {
                    "service": "App\\Logger\\JsonFormatter",
                    "tag": null
                }
            }
        },
        "App\\Cache\\RedisCache": {
            "concrete": "App\\Cache\\RedisCache",
            "mode": "scoped",
            "lazy": true,
            "scope": "request"
        }
    },

    "tags": {
        "exporter": ["App\\Export\\CsvExporter", "App\\Export\\PdfExporter"],
        "middleware": {
            "App\\Http\\AuthMiddleware": 100,
            "App\\Http\\CorsMiddleware": 50
        }
    },

    "contextual": {
        "App\\Service\\OrderService": {
            "App\\Logger\\LoggerInterface": "App\\Logger\\OrderLogger"
        }
    },

    "parameters": {
        "App\\Database\\Connection": {
            "timeout": 30,
            "charset": "utf8mb4"
        }
    },

    "aliases": {
        "logger": "App\\Logger\\LoggerInterface",
        "cache": "App\\Cache\\CacheInterface"
    }
}
```

### Section Reference

| Key | Maps to | Notes |
| --- | --- | --- |
| `scopes` | `registerScope()` | Registers named scopes |
| `services` | `bind()` | Each key is the abstract; `concrete` defaults to the abstract if omitted |
| `services[*].mode` | `BindingMode` | `"singleton"`, `"scoped"` *(default)*, `"transient"` |
| `services[*].lazy` | `bind()` `lazy` option | `true` / `false` |
| `services[*].scope` | `bind()` `scope` option | Named scope or `null` for current scope at load time |
| `services[*].tags` | `bind()` `tags` option | Map of `tagName => priority` or flat list (priority 0) |
| `services[*].constructorInjection` | `addConstructorInjection()` | Per-param `service` or `tag` override |
| `services[*].propertyInjection` | `addPropertyInjection()` | Per-property `service` or `tag` override |
| `tags` | `tag()` | Flat list → priority 0; map → explicit priority |
| `contextual` | `setContextualBinding()` | `consumer => { needs => concrete }` |
| `parameters` | `addParameter()` | `class => { paramName => value }` |
| `aliases` | `alias()` | `alias => abstract` |

---

## Warmup

```php
$container->warmup(): static
```

Pre-populates the reflection cache and runs attribute scanning for all registered concretes in a
single pass.  Call once after completing all registrations to eliminate per-request overhead on
the first resolution of each service.

```php
$container->loadConfig('config/services.json');
$container->warmup();
```

---

## Cache Export

```php
$container->exportCache(string $path) : static
```

Serialises the container's current state to a PHP file at `$path`. The following data is persisted:

- `aliases`
- `attributeCache` (prevents re-scanning on reload)
- `bindings` (closures are excluded — only string concretes are serialisable)
- `classParameters`
- `constructorInjectionMap`
- `propertyInjectionMap`

```php
$container->exportCache('/var/cache/container.php');
```

The generated file returns a plain PHP `array` via `return`. It is safe to include via `require`.

---

## Cache Import

```php
$container->loadCache(string $path) : static
```

Imports a previously exported cache file, merging all persisted data into the current container
state. Throws `ContainerException` if the file does not exist or does not return an array.

```php
$container->loadCache('/var/cache/container.php');
```

---

## Typical Boot Sequence

### Development (no cache)

```php
$container = new Container();
$container->loadConfig('config/services.json');
$container->warmup();
```

### Production (with cache)

```php
$container = new Container();

if (file_exists($cacheFile)) {
    $container->loadCache($cacheFile);
}
else {
    $container->loadConfig('config/services.json');
    $container->warmup();
    $container->exportCache($cacheFile);
}
```
