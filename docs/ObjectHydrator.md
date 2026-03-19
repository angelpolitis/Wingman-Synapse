# Object Hydrator

`Wingman\Synapse\ObjectHydrator` maps plain associative arrays to strongly typed PHP objects
(Data Transfer Objects). It is decoupled from the DI container and does not resolve services —
it maps data, not dependencies.

---

## Hydrating a Single Object

```php
$hydrator = new ObjectHydrator();

$dto = $hydrator->hydrate(string $class, array $data) : object
```

### Constructor-Based Hydration

When the target class has a constructor, parameters are matched from `$data` by name. Missing
optional parameters receive their default value. Missing required parameters throw a
`RuntimeException`.

```php
class CreateOrderDTO {
    public function __construct (
        public readonly string $customerId,
        public readonly int $quantity,
        public readonly string $currency = 'GBP',
    ) {}
}

$dto = $hydrator->hydrate(CreateOrderDTO::class, [
    'customerId' => 'cust_001',
    'quantity' => 3,
]);
// $dto->currency === 'GBP' (default)
```

### Property-Based Hydration

When the class has no constructor, each public property is assigned directly from the data
array (by name). Properties absent from the data are left uninitialised.

```php
class AddressDTO {
    public string $street;
    public string $city;
    public string $postcode;
}

$dto = $hydrator->hydrate(AddressDTO::class, [
    'street' => '10 Downing St',
    'city' => 'London',
    'postcode' => 'SW1A 2AA',
]);
```

---

## Nested DTOs

When a constructor parameter is typed as a class and `$data[$paramName]` is an array, the
hydrator recurses automatically:

```php
class OrderItemDTO {
    public function __construct (
        public readonly string $sku,
        public readonly int $quantity
    ) {}
}

class OrderDTO {
    public function __construct (
        public readonly string $id,
        public readonly OrderItemDTO $item
    ) {}
}

$dto = $hydrator->hydrate(OrderDTO::class, [
    'id' => 'ord_42',
    'item' => ['sku' => 'ABC-001', 'quantity' => 2],
]);
// $dto->item instanceof OrderItemDTO
```

---

## Hydrating Multiple Objects

```php
$dtos = $hydrator->hydrateMany(string $class, array $dataSet) : array
```

Equivalent to calling `hydrate()` for each element of `$dataSet`:

```php
$orders = $hydrator->hydrateMany(OrderDTO::class, $rows);
```

---

## Notes

- `ObjectHydrator` is a standalone utility — it can be instantiated and used independently of
  any `Container` instance.
- Type coercion is not performed. If the data value does not match the expected type, PHP's own
  type enforcement applies at construction or assignment.
- Only public properties are written in property-based hydration mode.
