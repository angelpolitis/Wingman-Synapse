# PSR-11 Bridge

Synapse ships an optional PSR-11 compatibility bridge. When `psr/container` is installed, the
bridge stubs extend the real PSR interfaces so Synapse's container and exceptions satisfy any
third-party code that type-checks against `Psr\Container`.  When `psr/container` is absent, the
stubs are empty marker interfaces — the container continues to work with no hard dependency.

---

## Container Compliance

`Container` implements both `Wingman\Synapse\Interfaces\ContainerInterface` and
`Wingman\Synapse\Bridge\PSR\ContainerInterface`.  The bridge interface extends
`Psr\Container\ContainerInterface` when available:

```php
use Psr\Container\ContainerInterface;

function bootstrap (ContainerInterface $container) : void {
    // Works with Synapse\Container when psr/container is installed.
}
```

`Container::get()` and `Container::has()` satisfy the PSR-11 method signatures exactly.

---

## Exception Hierarchy

Two concrete exception classes are provided:

### `ContainerException`

```
Wingman\Synapse\Bridge\PSR\ContainerException
  extends RuntimeException
  implements Bridge\PSR\ContainerExceptionInterface
    (extends Psr\Container\Exception\ContainerExceptionInterface when psr/container installed)
```

Thrown for general resolution failures:

- A class does not exist.
- A class is not instantiable (abstract, interface, private constructor).
- A constructor parameter cannot be resolved.
- A circular dependency is detected.
- A tag injection is attempted on a non-array property.
- A cache file is missing or malformed.

### `NotFoundException`

```
Wingman\Synapse\Bridge\PSR\NotFoundException
  extends ContainerException
  implements Bridge\PSR\NotFoundExceptionInterface
    (extends Psr\Container\Exception\NotFoundExceptionInterface when psr/container installed)
```

Thrown when an identifier cannot be found:

- Strict mode is enabled and `get()` / `make()` is called for an abstract with no explicit binding.
- A class name passed to `resolve()` does not exist.

---

## Catching Exceptions

Because `NotFoundException` extends `ContainerException`, all container failures can be caught
with a single catch block:

```php
use Wingman\Synapse\Bridge\PSR\ContainerException;
use Wingman\Synapse\Bridge\PSR\NotFoundException;

try {
    $service = $container->get(SomeService::class);
}
catch (NotFoundException $e) {
    // Abstract not registered; handle missing service.
}
catch (ContainerException $e) {
    // Resolution failed; handle build error.
}
```

Because both classes extend `RuntimeException`, existing code that catches `RuntimeException` is
unaffected.

---

## Installing `psr/container`

```bash
composer require psr/container
```

After installation, Synapse's bridge interfaces automatically become real `Psr\Container`
sub-types — no code changes are required.
