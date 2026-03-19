# Dependency Graph Analysis

The container accumulates a dependency graph as services are resolved. The graph is a map of
`className => [dependency1, dependency2, ...]` built from constructor type hints and property
injections.  A scoped graph is maintained per scope name in addition to the global graph.

---

## Accessing the Graph

### Global graph

```php
$graph = $container->getDependencyGraph() : array<string, string[]>
```

### Scoped graph

```php
$graph = $container->getDependencyGraph(scoped: true) : array<string, string[]>
// Returns the graph for the currently active scope.

$graph = $container->getScopedDependencyGraph(string $scope) : array<string, string[]>
// Returns the graph for a named scope.
```

---

## Querying Dependencies and Dependents

```php
$dependencies = $container->getDependencies(string $class, bool $scoped = false) : string[]
// Returns all direct dependencies of $class.

$dependants = $container->getDependents(string $class, bool $scoped = false) : string[]
// Returns all classes that directly depend on $class.
```

```php
$deps = $container->getDependencies(OrderService::class);
// ['LoggerInterface', 'OrderRepository', ...]
```

---

## Detecting Circular Dependencies

```php
$cycles = $container->detectCircularDependencies(bool $scoped = false): array<int, string[]>
```

Returns all cycles found in the graph.  Each cycle is an array of class names forming the loop,
with the first and last element being the same node:

```php
$cycles = $container->detectCircularDependencies();
// [['A', 'B', 'C', 'A'], ...]
```

Passing `true` analyses the current scope's graph instead of the global one.

---

## Full Dependency Tree

```php
$tree = $container->getFullDependencyGraph(string $abstract, ?string $scope = null, array &$visited = []) : array
```

Builds a rich, recursively expanded graph for a single abstract, including binding metadata and
subgraphs for every constructor parameter and injectable property. Returns an empty array when the
concrete class does not exist.

Output structure:

```php
[
    'concrete'   => 'App\\Service\\OrderService',
    'mode'       => 'scoped',
    'singleton'  => false,
    'lazy'       => false,
    'scope'      => 'global',
    'tags'       => [
        'universal' => ['commands' => 10],
        'scoped'    => [],
    ],
    'constructor' => [
        'logger' => [
            'dependency' => 'App\\Logger\\FileLogger',
            'mode'       => 'singleton',
            'singleton'  => true,
            'lazy'       => false,
            'scope'      => 'global',
            'tags'       => [...],
            'subgraph'   => [...],
        ],
    ],
    'properties' => [
        'mailer' => [...],
    ]
]
```

---

## Printing the Graph

```php
$container->printDependencyGraph(?string $scope = null) : static
```

Writes a human-readable summary of the graph to stdout.  Pass a scope name to print that scope's
graph instead.

---

## GraphAnalyser

`getGraphAnalyser()` returns a `GraphAnalyser` instance backed by the specified graph. You can
use it independently for analysis outside of a container:

```php
$analyser = $container->getGraphAnalyser(?string $scope = null) : GraphAnalyser

// Or standalone:
$analyser = new GraphAnalyser($customGraph);
```

`GraphAnalyser` exposes:

| Method | Description |
| --- | --- |
| `getDependencies(string $class)` | Direct dependencies of `$class` |
| `getDependents(string $class)` | Classes that list `$class` as a direct dependency |
| `detectCircularDependencies()` | All cycles (DFS; each cycle includes start = end node) |
| `printGraph()` | Human-readable summary to stdout |
