<?php
    /**
     * Project Name:    Wingman Synapse - Container
     * Created by:      Angel Politis
     * Creation Date:   Nov 28 2025
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse namespace.
    namespace Wingman\Synapse;

    # Import the following classes to the current scope.
    use Wingman\Synapse\Attributes\Bind as BindAttribute;
    use Wingman\Synapse\Attributes\Context as ContextAttribute;
    use Wingman\Synapse\Attributes\Inject as InjectAttribute;
    use Wingman\Synapse\Attributes\Service as ServiceAttribute;
    use Wingman\Synapse\Bridge\Cortex\Attributes\Configurable;
    use Wingman\Synapse\Bridge\Cortex\Configuration;
    use Wingman\Synapse\Bridge\Corvus\Emitter;
    use Wingman\Synapse\Bridge\PSR\ContainerException;
    use Wingman\Synapse\Bridge\PSR\ContainerInterface as ContainerBridgeInterface;
    use Wingman\Synapse\Bridge\PSR\NotFoundException;
    use Wingman\Synapse\Interfaces\Bind as BindInterface;
    use Wingman\Synapse\Interfaces\ContainerInterface;
    use Wingman\Synapse\Interfaces\Context as ContextInterface;
    use Wingman\Synapse\Interfaces\Service as ServiceInterface;
    use Wingman\Synapse\Enums\BindingMode;
    use Wingman\Synapse\Enums\Signal;
    use Wingman\Synapse\Enums\TagScope;
    use Wingman\Synapse\Traits\Bind as BindTrait;
    use Wingman\Synapse\Traits\Context as ContextTrait;
    use Wingman\Synapse\Traits\Service as ServiceTrait;
    use ReflectionClass;
    use ReflectionFunction;
    use ReflectionMethod;
    use ReflectionNamedType;
    use ReflectionProperty;
    use RuntimeException;

    /**
     * Represents a Synapse container.
     * @package Wingman\Synapse
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Container implements ContainerInterface, ContainerBridgeInterface {
        /**
         * The name of the global scope, which is always present and cannot be exited.
         * @var string
         */
        public const GLOBAL_SCOPE = "global";

        /**
         * Shared emitter instance used for all Corvus signal emissions.
         * @var Emitter
         */
        private Emitter $emitter;

        /**
         * Maps registered aliases to their corresponding abstract identifiers.
         * @var array<string, string>
         */
        protected array $aliases = [];

        /**
         * Tracks which classes have already had their attributes scanned and registered.
         * @var array<string, bool>
         */
        protected array $attributeCache = [];

        /**
         * Stores callbacks invoked right before an abstract is resolved via get() or make().
         * @var callable[]
         */
        protected array $beforeResolveCallbacks = [];

        /**
         * Stores PHP-side callbacks registered via onBind() for each abstract identifier.
         * The special key '*' holds callbacks that fire for every bind() call.
         * @var array<string, callable[]>
         */
        protected array $bindCallbacks = [];

        /**
         * Stores all registered bindings keyed by their abstract identifier.
         * @var array<string, array<string, mixed>>
         */
        protected array $bindings = [];

        /**
         * Stores scalar parameter overrides scoped to a specific class constructor.
         * @var array<string, array<string, mixed>>
         */
        protected array $classParameters = [];

        /**
         * Stores constructor injection overrides per class and parameter name.
         * @var array<string, array<string, mixed>>
         */
        protected array $constructorInjectionMap = [];

        /**
         * Stores contextual binding overrides keyed by consumer class, then by the needed abstract.
         * @var array<string, array<string, mixed>>
         */
        protected array $contextual = [];

        /**
         * The application-wide dependency graph, mapping each class name to its dependency list.
         * @var array<string, string[]>
         */
        protected array $dependencyGraph = [];

        /**
         * Stores registered decorator callables indexed by abstract identifier.
         * @var array<string, callable[]>
         */
        protected array $extenders = [];

        /**
         * Stores registered factory callables or objects indexed by service identifier.
         * @var array<string, mixed>
         */
        protected array $factories = [];

        /**
         * Controls whether property injection is attempted only on properties marked with #[Inject].
         * @var bool
         */
        #[Configurable("synapse.container.inject.attributesOnly", "Whether property injection should require the #[Inject] attribute.")]
        protected bool $injectableOnlyWithAttributes = false;

        /**
         * Controls whether protected properties are eligible for automatic injection.
         * @var bool
         */
        #[Configurable("synapse.container.inject.protected", "Whether protected properties are eligible for automatic injection.")]
        protected bool $injectProtected = false;

        /**
         * Stores resolved singleton instances keyed by abstract identifier.
         * @var array<string, object>
         */
        protected array $instances = [];

        /**
         * Caches lazy non-singleton instances per scope to prevent re-resolution within one scope lifetime.
         * @var array<string, array<string, object>>
         */
        protected array $lazyScopeCache = [];

        /**
         * Stores global scalar parameter values available as fallbacks for all constructor resolutions.
         * @var array<string, mixed>
         */
        protected array $parameters = [];

        /**
         * Stores property injection overrides per class and property name.
         * @var array<string, array<string, mixed>>
         */
        protected array $propertyInjectionMap = [];

        /**
         * Caches ReflectionClass instances by fully-qualified class name to prevent repeated instantiation.
         * @var array<string, ReflectionClass>
         */
        protected array $reflectionCache = [];

        /**
         * Stores lifecycle callbacks invoked after a service is fully decorated, keyed by abstract.
         * The special key '*' holds callbacks that run for every resolution.
         * @var array<string, callable[]>
         */
        protected array $resolvedCallbacks = [];

        /**
         * Tracks abstracts currently being resolved to detect circular dependencies.
         * @var array<string, bool>
         */
        protected array $resolving = [];

        /**
         * Stores lifecycle callbacks invoked right after a service is constructed,
         * before extenders are applied. The special key '*' holds callbacks for every resolution.
         * @var array<string, callable[]>
         */
        protected array $resolvingCallbacks = [];

        /**
         * The scope-partitioned dependency graph, mapping scope name to a class => dependency list map.
         * @var array<string, array<string, string[]>>
         */
        protected array $scopedDependencyGraph = [];

        /**
         * Stores scoped (non-singleton) instances partitioned by scope name.
         * @var array<string, array<string, object>>
         */
        protected array $scopeInstances = [];

        /**
         * The active scope name stack; the topmost entry is the current scope.
         * @var string[]
         */
        protected array $scopeStack = [self::GLOBAL_SCOPE];

        /**
         * Stores scope-specific tag associations, keyed by scope name, then tag name, then abstract.
         * @var array<string, array<string, array<string, mixed>>>
         */
        protected array $scopeTags = [];

        /**
         * Controls whether the container throws on any resolution that has no explicit binding.
         * When true, implicit autowiring of unregistered classes is disabled.
         * @var bool
         */
        #[Configurable("synapse.container.strict", "Whether to enforce strict mode and reject implicit autowiring.")]
        protected bool $strict = false;

        /**
         * Stores application-wide tag associations, keyed by tag name then abstract.
         * @var array<string, array<string, array<string, mixed>>>
         */
        protected array $tags = [];

        /**
         * Creates a new container.
         * @param array|Configuration $config Optional flat dot-notation config map, or a Cortex Configuration instance.
         * Supported keys: synapse.container.strict, synapse.container.inject.protected, synapse.container.inject.attributesOnly.
         */
        public function __construct (array|Configuration $config = []) {
            $this->emitter = Emitter::create();
            Configuration::hydrate($this, $config);
        }

        /**
         * Invokes all callbacks registered via onResolved() for the given abstract,
         * including any wildcard (*) callbacks, after extenders have been applied.
         * @param string $abstract The abstract identifier that was resolved.
         * @param mixed $instance The fully decorated instance.
         */
        private function fireResolvedCallbacks (string $abstract, mixed $instance) : void {
            foreach ($this->resolvedCallbacks['*'] ?? [] as $callback) {
                $callback($instance, $this);
            }

            foreach ($this->resolvedCallbacks[$abstract] ?? [] as $callback) {
                $callback($instance, $this);
            }
        }

        /**
         * Invokes all callbacks registered via onResolving() for the given abstract,
         * including any wildcard (*) callbacks, before extenders are applied.
         * @param string $abstract The abstract identifier being resolved.
         * @param mixed $instance The freshly constructed instance.
         */
        private function fireResolvingCallbacks (string $abstract, mixed $instance) : void {
            foreach ($this->resolvingCallbacks['*'] ?? [] as $callback) {
                $callback($instance, $this);
            }

            foreach ($this->resolvingCallbacks[$abstract] ?? [] as $callback) {
                $callback($instance, $this);
            }
        }

        /**
         * Collects the abstract–info map for a single tag name, merging the scope-specific
         * and universal tag registries with scope-specific entries taking precedence.
         * @param string $tag The tag name to look up.
         * @param string $scope The scope to query for scope-specific entries.
         * @return array<string, array<string, mixed>> Map of abstract identifier to tag info.
         */
        private function getAbstractsByTag (string $tag, string $scope) : array {
            $abstracts = [];

            foreach ($this->scopeTags[$scope][$tag] ?? [] as $abstract => $info) {
                $abstracts[$abstract] = $info;
            }

            foreach ($this->tags[$tag] ?? [] as $abstract => $info) {
                $abstracts[$abstract] ??= $info;
            }

            return $abstracts;
        }

        /**
         * Invokes callbacks registered via onBeforeResolving() before an abstract is resolved.
         * @param string $abstract The abstract identifier about to be resolved.
         */
        private function notifyBeforeResolving (string $abstract) : void {
            foreach ($this->beforeResolveCallbacks as $callback) {
                $callback($abstract, $this);
            }
        }

        /**
         * Processes class-level PHP attributes on a concrete class and registers any discovered
         * bindings or contextual overrides with the container.
         * @param string $concrete Fully-qualified class name to inspect.
         * @param ReflectionClass $ref Reflection instance for the concrete class.
         */
        private function registerAttributeTrack (string $concrete, ReflectionClass $ref) : void {
            foreach ($ref->getAttributes() as $attr) {
                $instance = $attr->newInstance();

                switch ($attr->getName()) {
                    case ServiceAttribute::class:
                        $scope = $instance->scope ?? $this->getCurrentScope();
                        $this->bindings[$concrete] = [
                            "concrete" => $concrete,
                            "mode" => $instance->singleton ? BindingMode::Singleton : BindingMode::Scoped,
                            "lazy" => false,
                            "scope" => $scope,
                        ];
                        break;

                    case BindAttribute::class:
                        $scope = $instance->scope ?? $this->getCurrentScope();
                        $this->bindings[$instance->abstract] = [
                            "concrete" => $concrete,
                            "mode" => $instance->singleton ? BindingMode::Singleton : BindingMode::Scoped,
                            "lazy" => $instance->lazy,
                            "scope" => $scope,
                        ];
                        break;

                    case ContextAttribute::class:
                        $this->contextual[$instance->consumer][$instance->needs] = [
                            "concrete" => $concrete,
                            "scope" => $instance->scope,
                        ];
                        break;
                }
            }
        }

        /**
         * Inspects the interfaces implemented by a concrete class and registers any service
         * bindings or contextual overrides derived from those contracts.
         * @param string $concrete Fully-qualified class name to inspect.
         * @param ReflectionClass $ref Reflection instance for the concrete class.
         * @throws RuntimeException If a required return value from an interface method is empty.
         */
        private function registerInterfaceTrack (string $concrete, ReflectionClass $ref) : void {
            foreach ($ref->getInterfaceNames() as $interface) {
                if ($interface === ServiceInterface::class) {
                    $scope = $this->getCurrentScope();
                    if (!isset($this->bindings[$concrete])) {
                        $this->bindings[$concrete] = [
                            "concrete" => $concrete,
                            "mode" => BindingMode::Singleton,
                            "lazy" => false,
                            "scope" => $scope,
                        ];
                    }
                } elseif ($interface === BindInterface::class) {
                    $scope = $this->getCurrentScope();
                    $bindInstance = $ref->newInstanceWithoutConstructor();
                    $abstract = $bindInstance->getAbstract();
                    if (empty($abstract)) {
                        throw new RuntimeException("$concrete implements Interfaces\\Bind but getAbstract() returned an empty value. Ensure the method returns a compile-time constant.");
                    }
                    if (!isset($this->bindings[$abstract])) {
                        $this->bindings[$abstract] = [
                            "concrete" => $concrete,
                            "mode" => BindingMode::Scoped,
                            "lazy" => false,
                            "scope" => $scope,
                        ];
                    }
                } elseif ($interface === ContextInterface::class) {
                    $ctxObj = $ref->newInstanceWithoutConstructor();
                    $consumer = $ctxObj->getConsumer();
                    $needs = $ctxObj->getNeeds();
                    if (empty($consumer) || empty($needs)) {
                        throw new RuntimeException("$concrete implements Interfaces\\Context but getConsumer() or getNeeds() returned an empty value. Ensure both methods return compile-time constants.");
                    }
                    $this->contextual[$consumer][$needs] = $concrete;
                }
            }
        }

        /**
         * Inspects the traits used by a concrete class and registers any service bindings
         * or contextual overrides derived from those traits.
         * @param string $concrete Fully-qualified class name to inspect.
         * @param ReflectionClass $ref Reflection instance for the concrete class.
         * @throws RuntimeException If a required return value from a trait method is empty.
         */
        private function registerTraitTrack (string $concrete, ReflectionClass $ref) : void {
            $traits = class_uses($concrete);

            if (isset($traits[ServiceTrait::class])) {
                $traitInstance = $ref->newInstanceWithoutConstructor();
                $traitScope = $traitInstance->getScope() ?? $this->getCurrentScope();
                if (!isset($this->bindings[$concrete])) {
                    $this->bindings[$concrete] = [
                        "concrete" => $concrete,
                        "mode" => $traitInstance->isSingleton() ? BindingMode::Singleton : BindingMode::Scoped,
                        "lazy" => false,
                        "scope" => $traitScope,
                    ];
                }
            }

            if (isset($traits[BindTrait::class])) {
                $scope = $this->getCurrentScope();
                $bindInstance = $ref->newInstanceWithoutConstructor();
                $abstract = $bindInstance->getAbstract();
                if (empty($abstract)) {
                    throw new RuntimeException("$concrete uses Traits\\Bind but getAbstract() returned an empty value. Ensure the method returns a compile-time constant.");
                }
                if (!isset($this->bindings[$abstract])) {
                    $this->bindings[$abstract] = [
                        "concrete" => $concrete,
                        "mode" => BindingMode::Scoped,
                        "lazy" => false,
                        "scope" => $scope,
                    ];
                }
            }

            if (isset($traits[ContextTrait::class])) {
                $ctxObj = $ref->newInstanceWithoutConstructor();
                $consumer = $ctxObj->getConsumer();
                $needs = $ctxObj->getNeeds();
                if (empty($consumer) || empty($needs)) {
                    throw new RuntimeException("$concrete uses Traits\\Context but getConsumer() or getNeeds() returned an empty value. Ensure both methods return compile-time constants.");
                }
                $this->contextual[$consumer][$needs] = $concrete;
            }
        }

        /**
         * Passes a resolved instance through all registered extender callables for the given abstract.
         * @param string $abstract The abstract identifier whose extenders to apply.
         * @param mixed $instance The freshly resolved instance.
         * @return mixed The instance after all extenders have been applied in registration order.
         */
        protected function applyExtenders (string $abstract, mixed $instance) : mixed {
            if (empty($this->extenders[$abstract])) {
                return $instance;
            }

            foreach ($this->extenders[$abstract] as $extender) {
                $instance = $extender($instance, $this);
            }

            return $instance;
        }

        /**
         * Resolves the concrete, fires the resolving lifecycle callbacks and Corvus signal, applies
         * extenders, then fires the resolved callbacks and Corvus signal before returning the instance.
         * @param string $abstract The abstract identifier being built.
         * @param string|callable $concrete The concrete class name or factory callable.
         * @return mixed The fully decorated resolved instance.
         */
        protected function build (string $abstract, string|callable $concrete) : mixed {
            $instance = $this->resolve($concrete);
            $this->fireResolvingCallbacks($abstract, $instance);
            $this->emitter->emit(Signal::RESOLVING)->with(id: $abstract);
            $instance = $this->applyExtenders($abstract, $instance);
            $this->fireResolvedCallbacks($abstract, $instance);
            $this->emitter->emit(Signal::RESOLVED)->with(id: $abstract);
            return $instance;
        }

        /**
         * Collects tags for a given abstract from the universal or scope-specific tag registry.
         * @param string $abstract The service abstract to look up.
         * @param TagScope $type Lookup strategy — universal tags or scope-specific tags.
         * @param string|null $scope Required when $type is TagScope::Scoped.
         * @return array<string, int> An associative map of tag name to priority.
         */
        protected function collectTags (string $abstract, TagScope $type = TagScope::Universal, ?string $scope = null) : array {
            $tags = [];

            if ($type === TagScope::Universal && !empty($this->tags)) {
                foreach ($this->tags as $tag => $entries) {
                    if (isset($entries[$abstract])) {
                        $tags[$tag] = $entries[$abstract]["priority"];
                    }
                }
            } elseif ($type === TagScope::Scoped && $scope !== null && isset($this->scopeTags[$scope])) {
                foreach ($this->scopeTags[$scope] as $tag => $entries) {
                    if (isset($entries[$abstract])) {
                        $tags[$tag] = $entries[$abstract]["priority"];
                    }
                }
            }

            return $tags;
        }

        /**
         * Returns the name of the currently active scope.
         * @return string The current scope name.
         */
        protected function getCurrentScope () : string {
            return end($this->scopeStack) ?: static::GLOBAL_SCOPE;
        }

        /**
         * Automatically injects dependencies into public (and optionally protected) properties,
         * tracking all injected dependencies in the dependency graph.
         * @param object $instance The object to inject into.
         */
        protected function injectProperties (object $instance) : void {
            $ref = $this->reflectClass($instance::class);
            $properties = $ref->getProperties(ReflectionProperty::IS_PUBLIC);
            $scope = $this->getCurrentScope();
            $class = $instance::class;

            if ($this->injectProtected) {
                $properties = array_merge($properties, $ref->getProperties(ReflectionProperty::IS_PROTECTED));
            }

            foreach ($properties as $prop) {
                $attributes = $prop->getAttributes(InjectAttribute::class);

                if ($prop->isInitialized($instance) && empty($attributes)) {
                    continue;
                }

                $propName = $prop->getName();

                if ($this->injectableOnlyWithAttributes && empty($attributes)) {
                    continue;
                }

                $serviceClass = null;
                $tag = null;

                if (!empty($attributes)) {
                    $injectAttr = $attributes[0]->newInstance();
                    $serviceClass = $injectAttr->service;
                    $tag = $injectAttr->tag;
                }

                $type = $prop->getType();

                if ($tag !== null) {
                    if ($type instanceof ReflectionNamedType && $type->getName() === "array") {
                        $prop->setValue($instance, $this->getByTag($tag, scope: $scope));
                        continue;
                    }

                    throw new ContainerException("Cannot inject multiple services into non-array property \${$prop->getName()}");
                }

                if (!$serviceClass) {
                    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                        $serviceClass = $type->getName();
                    } elseif ($this->injectableOnlyWithAttributes) {
                        throw new ContainerException("Cannot determine service to inject for \${$prop->getName()}");
                    } else {
                        continue;
                    }
                }

                if (isset($this->propertyInjectionMap[$class][$propName])) {
                    $configRule = $this->propertyInjectionMap[$class][$propName];
                    $serviceClass = $configRule["service"] ?? $serviceClass;
                    $tag = $configRule["tag"] ?? $tag;
                }

                if ($tag !== null) {
                    if ($type instanceof ReflectionNamedType && $type->getName() === "array") {
                        $prop->setValue($instance, $this->getByTag($tag, scope: $scope));
                        continue;
                    }

                    throw new ContainerException("Cannot inject multiple services into non-array property \${$prop->getName()}");
                }

                $prop->setValue($instance, $this->get($serviceClass));

                if (!in_array($serviceClass, $this->dependencyGraph[$class] ?? [], true)) {
                    $this->dependencyGraph[$class][] = $serviceClass;
                }

                if (!in_array($serviceClass, $this->scopedDependencyGraph[$scope][$class] ?? [], true)) {
                    $this->scopedDependencyGraph[$scope][$class][] = $serviceClass;
                }
            }
        }

        /**
         * Returns a cached ReflectionClass instance for the given class name, instantiating it on first use.
         * @param string $fqcn Fully-qualified class name to reflect.
         * @return ReflectionClass
         */
        protected function reflectClass (string $fqcn) : ReflectionClass {
            return $this->reflectionCache[$fqcn] ??= new ReflectionClass($fqcn);
        }

        /**
         * Scans the class-level attributes, interfaces, and traits of a concrete class and registers
         * any discovered bindings, contextual overrides, or service declarations with the container
         * automatically on first resolution.
         * @param string $concrete Fully-qualified class name to inspect.
         */
        protected function registerAttributes (string $concrete) : void {
            if (!class_exists($concrete)) {
                return;
            }

            $ref = $this->reflectClass($concrete);
            $this->registerAttributeTrack($concrete, $ref);
            $this->registerInterfaceTrack($concrete, $ref);
            $this->registerTraitTrack($concrete, $ref);
        }

        /**
         * Resolves a concrete class name or callable into a fully instantiated and injected object.
         * @param string|callable $concrete A class name or factory callable.
         * @throws RuntimeException If the class does not exist, is not instantiable, or a dependency cannot be resolved.
         * @return mixed The resolved instance.
         */
        protected function resolve (string|callable $concrete) : mixed {
            if (is_callable($concrete)) {
                return $concrete($this);
            }

            if (!class_exists($concrete)) {
                throw new NotFoundException("Cannot resolve class '$concrete'; class does not exist.");
            }

            if (!isset($this->attributeCache[$concrete])) {
                $this->attributeCache[$concrete] = true;
                $this->registerAttributes($concrete);
            }

            $ref = $this->reflectClass($concrete);

            if (!$ref->isInstantiable()) {
                throw new ContainerException("$concrete not instantiable");
            }

            $ctor = $ref->getConstructor();
            $scope = $this->getCurrentScope();

            if (!$ctor) {
                $object = new $concrete;
            } else {
                $args = [];

                foreach ($ctor->getParameters() as $param) {
                    $paramName = $param->getName();
                    $attrs = $param->getAttributes(InjectAttribute::class);
                    $shouldInject = !$this->injectableOnlyWithAttributes || !empty($attrs);

                    if ($shouldInject && !empty($attrs)) {
                        /** @var InjectAttribute $inject */
                        $inject = $attrs[0]->newInstance();

                        if ($inject->tag !== null) {
                            $args[] = $this->getByTag($inject->tag, scope: $scope);
                            continue;
                        }

                        if ($inject->service !== null) {
                            $args[] = $this->get($inject->service);
                            continue;
                        }
                    }

                    $type = $param->getType();

                    if ($shouldInject && $type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                        $dependency = $type->getName();

                        if (isset($this->constructorInjectionMap[$concrete][$paramName])) {
                            $configRule = $this->constructorInjectionMap[$concrete][$paramName];

                            if (!empty($configRule["service"])) {
                                $args[] = $this->get($configRule["service"]);
                                continue;
                            }

                            if (!empty($configRule["tag"])) {
                                $args[] = $this->getByTag($configRule["tag"], scope: $scope);
                                continue;
                            }
                        }

                        if (isset($this->contextual[$concrete][$dependency])) {
                            $ctxEntry = $this->contextual[$concrete][$dependency];
                            $ctxConcrete = null;

                            if (is_array($ctxEntry)) {
                                $ctxScope = $ctxEntry["scope"] ?? null;
                                if ($ctxScope === null || $ctxScope === $this->getCurrentScope()) {
                                    $ctxConcrete = $ctxEntry["concrete"];
                                }
                            } else {
                                $ctxConcrete = $ctxEntry;
                            }

                            if ($ctxConcrete !== null) {
                                $args[] = is_object($ctxConcrete)
                                    ? $ctxConcrete
                                    : (is_callable($ctxConcrete) ? $ctxConcrete($this) : $this->get($ctxConcrete));
                                continue;
                            }
                        }

                        $args[] = $this->get($this->bindings[$dependency]["concrete"] ?? $dependency);
                        continue;
                    }

                    if (isset($this->classParameters[$concrete][$paramName])) {
                        $args[] = $this->classParameters[$concrete][$paramName];
                    } elseif (isset($this->parameters[$paramName])) {
                        $args[] = $this->parameters[$paramName];
                    } elseif ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                    } else {
                        throw new ContainerException("Cannot resolve parameter \${$param->name} for $concrete");
                    }
                }

                $object = $ref->newInstanceArgs($args);
            }

            $class = $object::class;
            $constructorDependencies = [];

            if ($ctor) {
                foreach ($ctor->getParameters() as $param) {
                    $paramType = $param->getType();
                    if ($paramType instanceof ReflectionNamedType && !$paramType->isBuiltin()) {
                        $constructorDependencies[] = $paramType->getName();
                    }
                }
            }

            if (!empty($constructorDependencies)) {
                $this->dependencyGraph[$class] = array_values(array_unique(array_merge(
                    $this->dependencyGraph[$class] ?? [],
                    $constructorDependencies
                )));
                $this->scopedDependencyGraph[$scope][$class] = array_values(array_unique(array_merge(
                    $this->scopedDependencyGraph[$scope][$class] ?? [],
                    $constructorDependencies
                )));
            }

            $this->injectProperties($object);

            return $object;
        }

        /**
         * Registers constructor injection metadata for a service.
         * @param string $abstract The service abstract.
         * @param string $param The constructor parameter name.
         * @param string|null $service Optional service abstract to inject.
         * @param string|null $tag Optional tag whose first result is injected.
         */
        public function addConstructorInjection (string $abstract, string $param, ?string $service, ?string $tag) : static {
            $this->constructorInjectionMap[$abstract][$param] = compact("service", "tag");
            return $this;
        }

        /**
         * Sets a scalar parameter value for a specific class constructor parameter.
         * @param string $class Fully-qualified class name.
         * @param string $param Constructor parameter name.
         * @param mixed $value The value to inject.
         */
        public function addParameter (string $class, string $param, mixed $value) : static {
            $this->classParameters[$class][$param] = $value;
            return $this;
        }

        /**
         * Registers property injection metadata for a service.
         * @param string $abstract The service abstract.
         * @param string $property The property name to inject into.
         * @param string|null $service Optional service abstract to inject.
         * @param string|null $tag Optional tag whose first result is injected.
         */
        public function addPropertyInjection (string $abstract, string $property, ?string $service, ?string $tag) : static {
            $this->propertyInjectionMap[$abstract][$property] = compact("service", "tag");
            return $this;
        }

        /**
         * Registers an alias for a service abstract, allowing it to be resolved by an alternative identifier.
         * @param string $alias The alias to register.
         * @param string $abstract The abstract the alias points to.
         */
        public function alias (string $alias, string $abstract) : static {
            $this->aliases[$alias] = $abstract;
            return $this;
        }

        /**
         * Registers a callback invoked right before any abstract is resolved.
         * @param callable $callback Callback receiving abstract identifier and container.
         * @return static
         */
        public function onBeforeResolving (callable $callback) : static {
            $this->beforeResolveCallbacks[] = $callback;
            return $this;
        }

        /**
         * Registers a binding between an abstract identifier and a concrete implementation.
         * @param string $abstract The abstract identifier to bind.
         * @param string|callable $concrete The concrete class name or factory callable.
         * @param array{mode: BindingMode, lazy: bool, scope: string|null, tags: string[]} $options Optional binding options.
         */
        public function bind (string $abstract, string|callable $concrete, array $options = []) : static {
            $mode = $options["mode"] ?? BindingMode::Scoped;
            $this->bindings[$abstract] = [
                "concrete" => $concrete,
                "mode" => $mode,
                "lazy" => $options["lazy"] ?? false,
                "scope" => $options["scope"] ?? null,
            ];

            if (!empty($options["tags"])) {
                $scope = $options["scope"] ?? $this->getCurrentScope();

                foreach ($options["tags"] as $tag => $priority) {
                    if (!is_numeric($priority)) {
                        $tagName = $priority;
                        $priority = 0;
                    } else {
                        $tagName = $tag;
                    }

                    $this->tags[$tagName][$abstract] = ["abstract" => $abstract, "priority" => $priority];
                    $this->scopeTags[$scope][$tagName][$abstract] = ["abstract" => $abstract, "priority" => $priority];
                }
            }

            $this->emitter->emit(Signal::BOUND)->with(id: $abstract);

            foreach ($this->bindCallbacks['*'] ?? [] as $callback) {
                $callback($abstract, $concrete, $this);
            }

            foreach ($this->bindCallbacks[$abstract] ?? [] as $callback) {
                $callback($abstract, $concrete, $this);
            }

            return $this;
        }

        /**
         * Registers a condition-guarded binding — delegates to bind() only when $condition is true.
         * @param bool $condition Whether to register the binding.
         * @param string $abstract The abstract identifier to bind.
         * @param string|callable $concrete The concrete class name or factory callable.
         * @param array $options Optional binding options.
         */
        public function bindIf (bool $condition, string $abstract, string|callable $concrete, array $options = []) : static {
            if ($condition) {
                $this->bind($abstract, $concrete, $options);
            }

            return $this;
        }

        /**
         * Registers a binding as a scoped lazy singleton — resolved on first access per scope
         * and discarded when the scope exits.
         * @param string $abstract The abstract identifier to bind.
         * @param string|callable $concrete The concrete class name or factory callable.
         * @param array $options Additional binding options (scope, tags, etc.).
         */
        public function bindLazy (string $abstract, string|callable $concrete, array $options = []) : static {
            $options["mode"] = BindingMode::Singleton;
            $options["lazy"] = true;
            $this->bind($abstract, $concrete, $options);
            return $this;
        }

        /**
         * Registers a condition-guarded lazy singleton binding — delegates to bindLazy() only when $condition is true.
         * @param bool $condition Whether to register the binding.
         * @param string $abstract The abstract identifier to bind.
         * @param string|callable $concrete The concrete class name or factory callable.
         * @param array $options Optional binding options.
         */
        public function bindLazyIf (bool $condition, string $abstract, string|callable $concrete, array $options = []) : static {
            if ($condition) {
                $this->bindLazy($abstract, $concrete, $options);
            }

            return $this;
        }

        /**
         * Registers a binding as a singleton — the same instance is returned on every resolution.
         * @param string $abstract The abstract identifier to bind.
         * @param string|callable $concrete The concrete class name or factory callable.
         * @param array $options Additional binding options (scope, tags, etc.).
         */
        public function bindSingleton (string $abstract, string|callable $concrete, array $options = []) : static {
            $options["mode"] = BindingMode::Singleton;
            $this->bind($abstract, $concrete, $options);
            return $this;
        }

        /**
         * Registers a condition-guarded singleton binding — delegates to bindSingleton() only when $condition is true.
         * @param bool $condition Whether to register the binding.
         * @param string $abstract The abstract identifier to bind.
         * @param string|callable $concrete The concrete class name or factory callable.
         * @param array $options Optional binding options.
         */
        public function bindSingletonIf (bool $condition, string $abstract, string|callable $concrete, array $options = []) : static {
            if ($condition) {
                $this->bindSingleton($abstract, $concrete, $options);
            }

            return $this;
        }

        /**
         * Registers a binding as transient — a fresh instance is built on every call to get().
         * The instance is never stored in any cache. Equivalent to always calling make().
         * @param string $abstract The abstract identifier to bind.
         * @param string|callable $concrete The concrete class name or factory callable.
         * @param array $options Additional binding options (scope, tags, etc.).
         */
        public function bindTransient (string $abstract, string|callable $concrete, array $options = []) : static {
            $options["mode"] = BindingMode::Transient;
            $options["lazy"] = false;
            $this->bind($abstract, $concrete, $options);
            return $this;
        }

        /**
         * Registers a condition-guarded transient binding — delegates to bindTransient() only when $condition is true.
         * @param bool $condition Whether to register the binding.
         * @param string $abstract The abstract identifier to bind.
         * @param string|callable $concrete The concrete class name or factory callable.
         * @param array $options Optional binding options.
         */
        public function bindTransientIf (bool $condition, string $abstract, string|callable $concrete, array $options = []) : static {
            if ($condition) {
                $this->bindTransient($abstract, $concrete, $options);
            }

            return $this;
        }

        /**
         * Invokes a callable, resolving all unspecified typed arguments from the container.
         * Contextual bindings registered for the callable's declaring class are honoured.
         * @param callable $callable The callable to invoke.
         * @param array $params Named parameter overrides.
         * @throws RuntimeException If a parameter cannot be resolved.
         * @return mixed The return value of the callable.
         */
        public function call (callable $callable, array $params = []) : mixed {
            $ref = is_array($callable)
                ? new ReflectionMethod($callable[0], $callable[1])
                : new ReflectionFunction($callable);

            $consumer = ($ref instanceof ReflectionMethod)
                ? $ref->getDeclaringClass()->getName()
                : null;

            $args = [];

            foreach ($ref->getParameters() as $param) {
                $name = $param->getName();
                $type = $param->getType();

                if (array_key_exists($name, $params)) {
                    $args[] = $params[$name];
                    continue;
                }

                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $dependency = $type->getName();

                    if ($consumer !== null && isset($this->contextual[$consumer][$dependency])) {
                        $ctxEntry = $this->contextual[$consumer][$dependency];
                        $ctxConcrete = null;

                        if (is_array($ctxEntry)) {
                            $ctxScope = $ctxEntry["scope"] ?? null;
                            if ($ctxScope === null || $ctxScope === $this->getCurrentScope()) {
                                $ctxConcrete = $ctxEntry["concrete"];
                            }
                        } else {
                            $ctxConcrete = $ctxEntry;
                        }

                        if ($ctxConcrete !== null) {
                            $args[] = is_object($ctxConcrete)
                                ? $ctxConcrete
                                : (is_callable($ctxConcrete) ? $ctxConcrete($this) : $this->get($ctxConcrete));
                            continue;
                        }
                    }

                    $args[] = $this->get($dependency);
                    continue;
                }

                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                    continue;
                }

                throw new RuntimeException("Cannot resolve parameter \${$name} for callable");
            }

            if ($ref instanceof ReflectionMethod) {
                $target = is_array($callable) && is_object($callable[0]) ? $callable[0] : null;
                return $ref->invokeArgs($target, $args);
            }

            return $ref->invokeArgs($args);
        }

        /**
         * Creates a new invoker for this container.
         * @return Invoker A new invoker scoped to this container.
         */
        public function createInvoker () : Invoker {
            return new Invoker($this);
        }

        /**
         * Creates a lazy proxy for the given abstract.
         *
         * On PHP 8.4+, a native lazy proxy is generated via ReflectionClass::newLazyProxy() for
         * non-final, non-abstract classes, guaranteeing type compatibility. For earlier PHP versions
         * or final/abstract classes, a magic-method fallback LazyProxy is used; note that the
         * fallback cannot satisfy strict PHP type hints at injection points.
         * @param string $abstract The abstract identifier to proxy.
         * @return object A lazy proxy that resolves the service on first method invocation.
         */
        public function createProxy (string $abstract) : object {
            $concrete = $this->bindings[$abstract]["concrete"] ?? $abstract;

            if (PHP_VERSION_ID >= 80400 && class_exists($concrete)) {
                $ref = $this->reflectClass($concrete);
                if (!$ref->isFinal() && !$ref->isAbstract()) {
                    return $ref->newLazyProxy(fn (object $_proxy) => $this->get($abstract));
                }
            }

            return new LazyProxy($this, $abstract);
        }

        /**
         * Detects all circular dependency cycles in the dependency graph.
         * @param bool $scoped Whether to analyse only the current scope's graph.
         * @return array<int, string[]> All detected cycles.
         */
        public function detectCircularDependencies (bool $scoped = false) : array {
            return $this->getGraphAnalyser($scoped ? $this->getCurrentScope() : null)->detectCircularDependencies();
        }

        /**
         * Pushes a new scope onto the scope stack, making it the active scope.
         * @param string $scopeName The scope name to enter.
         */
        public function enterScope (string $scopeName) : static {
            $this->scopeStack[] = $scopeName;
            $this->emitter->emit(Signal::SCOPE_ENTERED)->with(scope: $scopeName);
            return $this;
        }

        /**
         * Pops the current scope off the scope stack and clears all instance caches associated with it.
         * @throws RuntimeException If an attempt is made to exit the global scope.
         */
        public function exitScope () : static {
            if (count($this->scopeStack) <= 1) {
                throw new RuntimeException("Cannot exit the global scope.");
            }

            $scopeName = array_pop($this->scopeStack);

            unset($this->scopeInstances[$scopeName]);
            unset($this->lazyScopeCache[$scopeName]);
            $this->emitter->emit(Signal::SCOPE_EXITED)->with(scope: $scopeName);
            return $this;
        }

        /**
         * Registers a decorator for a bound abstract.
         *
         * The decorator receives the resolved instance and the container, and must return the
         * decorated instance. Multiple decorators are applied in registration order on each
         * fresh resolution.
         * @param string $abstract The service abstract to decorate.
         * @param callable $decorator A callable that receives the resolved instance and the container, and returns the decorated instance.
         */
        public function extend (string $abstract, callable $decorator) : static {
            $this->extenders[$abstract][] = $decorator;
            return $this;
        }

        /**
         * Exports a serialised snapshot of the container's bindings, aliases, injection maps, and
         * attribute cache to a PHP file. Bindings whose concrete is a closure are excluded, because
         * PHP cannot serialise closures. Re-import the file with loadCache() on subsequent boots to
         * restore all configuration without re-parsing JSON files or repeating attribute scans.
         * @param string $path Absolute path of the cache file to write.
         * @throws RuntimeException If the file cannot be written.
         */
        public function exportCache (string $path) : static {
            $state = [
                "aliases" => $this->aliases,
                "attributeCache" => $this->attributeCache,
                "bindings" => array_filter($this->bindings, fn ($b) => is_string($b["concrete"] ?? null)),
                "classParameters" => $this->classParameters,
                "constructorInjectionMap" => $this->constructorInjectionMap,
                "propertyInjectionMap" => $this->propertyInjectionMap,
            ];

            $php = "<?php\nreturn " . var_export($state, true) . ";\n";

            if (file_put_contents($path, $php) === false) {
                throw new RuntimeException("Failed to write container cache to '$path'.");
            }

            return $this;
        }

        /**
         * Returns a contextual binding builder for the given consumer class, starting the
         * fluent forConsumer()->needs()->give() declaration chain.
         * @param string $consumer The class that will receive the contextual override.
         * @return ContextualBindingBuilder A builder for configuring the contextual binding.
         */
        public function forConsumer (string $consumer) : ContextualBindingBuilder {
            return new ContextualBindingBuilder($this, $consumer);
        }

        /**
         * Removes an abstract from all container registries, clearing any cached instance, binding
         * record, attribute cache entry, extenders, lifecycle callbacks, and tag associations for it.
         * @param string $abstract The abstract identifier to remove.
         */
        public function forget (string $abstract) : static {
            unset(
                $this->bindings[$abstract],
                $this->instances[$abstract],
                $this->attributeCache[$abstract],
                $this->bindCallbacks[$abstract],
                $this->constructorInjectionMap[$abstract],
                $this->contextual[$abstract],
                $this->extenders[$abstract],
                $this->factories[$abstract],
                $this->propertyInjectionMap[$abstract],
                $this->resolvedCallbacks[$abstract],
                $this->resolvingCallbacks[$abstract]
            );

            foreach ($this->aliases as $alias => $target) {
                if ($target === $abstract) {
                    unset($this->aliases[$alias]);
                }
            }

            foreach (array_keys($this->scopeInstances) as $scope) {
                unset($this->scopeInstances[$scope][$abstract]);
            }

            foreach (array_keys($this->lazyScopeCache) as $scope) {
                unset($this->lazyScopeCache[$scope][$abstract]);
            }

            foreach ($this->tags as &$tagEntries) {
                unset($tagEntries[$abstract]);
            }

            foreach ($this->scopeTags as &$scopeEntries) {
                foreach ($scopeEntries as &$tagEntries) {
                    unset($tagEntries[$abstract]);
                }
            }

            return $this;
        }

        /**
         * Creates a child container that inherits all bindings, aliases, contextual rules, tags,
         * factories, parameters, extenders, and injection configuration from this container,
         * but maintains its own independent instance store.
         *
         * Bindings overridden on the child have no effect on the parent. The child starts with
         * an empty resolved-instance cache, so parent singletons are not shared into the child
         * and vice versa. Lifecycle callbacks and dependency graphs are not inherited.
         * @return static A new container pre-populated with this container's configuration.
         */
        public function fork () : static {
            $child = new static();
            $child->aliases = $this->aliases;
            $child->attributeCache = $this->attributeCache;
            $child->bindCallbacks = $this->bindCallbacks;
            $child->bindings = $this->bindings;
            $child->classParameters = $this->classParameters;
            $child->constructorInjectionMap = $this->constructorInjectionMap;
            $child->contextual = $this->contextual;
            $child->extenders = $this->extenders;
            $child->factories = $this->factories;
            $child->injectableOnlyWithAttributes = $this->injectableOnlyWithAttributes;
            $child->injectProtected = $this->injectProtected;
            $child->parameters = $this->parameters;
            $child->propertyInjectionMap = $this->propertyInjectionMap;
            $child->reflectionCache = $this->reflectionCache;
            $child->scopeTags = $this->scopeTags;
            $child->strict = $this->strict;
            $child->tags = $this->tags;
            return $child;
        }

        /**
         * Finds an entry in the container by its identifier and returns it.
         * @param string $abstract Identifier of the entry to look up.
         * @throws RuntimeException If the identifier cannot be resolved or a circular dependency is detected.
         * @return mixed The resolved entry.
         */
        public function get (string $abstract) : mixed {
            $abstract = $this->aliases[$abstract] ?? $abstract;
            $this->notifyBeforeResolving($abstract);
            $scopeName = $this->getCurrentScope();

            for ($i = count($this->scopeStack) - 1; $i >= 0; $i--) {
                $stackName = $this->scopeStack[$i];
                if (isset($this->scopeInstances[$stackName][$abstract])) {
                    return $this->scopeInstances[$stackName][$abstract];
                }
            }

            if (isset($this->instances[$abstract])) {
                return $this->instances[$abstract];
            }

            if (isset($this->resolving[$abstract])) {
                throw new ContainerException("Circular dependency: $abstract");
            }

            $this->resolving[$abstract] = true;

            try {
                $binding = $this->bindings[$abstract] ?? [];
                $concrete = $binding["concrete"] ?? $abstract;
                $mode = $binding["mode"] ?? BindingMode::Scoped;
                $isLazy = $binding["lazy"] ?? false;
                $targetScope = $binding["scope"] ?? $scopeName;

                if (empty($binding) && isset($this->factories[$abstract])) {
                    return $this->build($abstract, $this->factories[$abstract]);
                }

                if (empty($binding) && $this->strict) {
                    throw new NotFoundException("Strict mode: '$abstract' has no explicit binding registered.");
                }

                if ($mode === BindingMode::Singleton && $isLazy) {
                    if (!isset($this->instances[$abstract])) {
                        $this->instances[$abstract] = $this->build($abstract, $concrete);
                    }

                    return $this->instances[$abstract];
                }

                if ($isLazy && $mode === BindingMode::Scoped) {
                    if (!isset($this->lazyScopeCache[$targetScope][$abstract])) {
                        $this->lazyScopeCache[$targetScope][$abstract] = $this->build($abstract, $concrete);
                    }

                    return $this->lazyScopeCache[$targetScope][$abstract];
                }

                if ($mode === BindingMode::Transient) {
                    return $this->build($abstract, $concrete);
                }

                $object = $this->build($abstract, $concrete);

                if ($mode === BindingMode::Singleton) {
                    $this->instances[$abstract] = $object;
                } else {
                    $this->scopeInstances[$targetScope][$abstract] = $object;
                }

                return $object;
            } finally {
                unset($this->resolving[$abstract]);
            }
        }

        /**
         * Returns all services matching any of the given tags, sorted by descending priority.
         * @param array|string $tags A single tag name or array of tag names.
         * @param callable|null $filter An optional filter callable; receives each resolved instance and returns a boolean.
         * @param string|null $scope The scope to query; defaults to the current scope.
         * @return array The resolved service instances.
         */
        public function getByTag (array|string $tags, ?callable $filter = null, ?string $scope = null) : array {
            $tags = (array) $tags;
            $scope = $scope ?? $this->getCurrentScope();
            $services = [];

            foreach ($tags as $tag) {
                foreach ($this->getAbstractsByTag($tag, $scope) as $abstract => $info) {
                    $services[$abstract] ??= $info;
                }
            }

            usort($services, fn ($a, $b) => $b["priority"] <=> $a["priority"]);

            $instances = [];

            foreach ($services as $item) {
                $instances[] = $this->get($item["abstract"]);
            }

            if ($filter) {
                $instances = array_filter($instances, $filter);
            }

            return array_values($instances);
        }

        /**
         * Returns the container's default (global) scope name.
         * @return string The default scope name.
         */
        public function getDefaultScope () : string {
            return static::GLOBAL_SCOPE;
        }

        /**
         * Returns all direct dependencies of the given class.
         * @param string $class Fully-qualified class name.
         * @param bool $scoped Whether to query the current scope's graph instead of the global graph.
         * @return string[] Class names this class depends on.
         */
        public function getDependencies (string $class, bool $scoped = false) : array {
            return $this->getGraphAnalyser($scoped ? $this->getCurrentScope() : null)->getDependencies($class);
        }

        /**
         * Returns the dependency graph, optionally restricted to the current scope.
         * @param bool $scoped Whether to return the current scope's graph instead of the global graph.
         * @return array<string, string[]> Map of class to dependencies.
         */
        public function getDependencyGraph (bool $scoped = false) : array {
            if ($scoped) {
                $scope = $this->getCurrentScope();
                return $this->scopedDependencyGraph[$scope] ?? [];
            }

            return $this->dependencyGraph;
        }

        /**
         * Returns all classes that directly depend on the given class.
         * @param string $class Fully-qualified class name.
         * @param bool $scoped Whether to query the current scope's graph instead of the global graph.
         * @return string[] Class names that depend on $class.
         */
        public function getDependents (string $class, bool $scoped = false) : array {
            return $this->getGraphAnalyser($scoped ? $this->getCurrentScope() : null)->getDependents($class);
        }

        /**
         * Returns the factory callable registered for the given service identifier, or null if none.
         * @param string $id The service identifier.
         * @return mixed The registered factory, or null.
         */
        public function getFactoryFor (string $id) : mixed {
            return $this->factories[$id] ?? null;
        }

        /**
         * Returns a rich, read-only dependency graph for a class, including tags, singletons, scopes,
         * and recursively expanded subgraphs for all constructor and property dependencies.
         * @param string $abstract Class or interface name.
         * @param string|null $scope Optional scope; defaults to the current scope.
         * @param array $visited Internal recursion guard; do not pass externally.
         * @return array
         */
        public function getFullDependencyGraph (string $abstract, ?string $scope = null, array &$visited = []) : array {
            $scope = $scope ?? $this->getCurrentScope();

            if (in_array($abstract, $visited, true)) {
                return [];
            }

            $visited[] = $abstract;

            $binding = $this->bindings[$abstract] ?? [];
            $concrete = $binding["concrete"] ?? $abstract;
            $mode = $binding["mode"] ?? BindingMode::Scoped;
            $isLazy = $binding["lazy"] ?? false;
            $bindingScope = $binding["scope"] ?? $scope;

            $globalTags = [];
            foreach ($this->tags as $tag => $entries) {
                if (isset($entries[$abstract])) {
                    $globalTags[$tag] = $entries[$abstract]["priority"];
                }
            }

            $scopedTagsForAbstract = [];
            foreach ($this->scopeTags[$bindingScope] ?? [] as $tag => $entries) {
                if (isset($entries[$abstract])) {
                    $scopedTagsForAbstract[$tag] = $entries[$abstract]["priority"];
                }
            }

            if (!class_exists($concrete)) {
                return [];
            }

            $graph = [
                "concrete" => $concrete,
                "mode" => $mode->value,
                "singleton" => $mode === BindingMode::Singleton,
                "lazy" => $isLazy,
                "scope" => $bindingScope,
                "tags" => [
                    TagScope::Universal->value => $globalTags,
                    TagScope::Scoped->value => $scopedTagsForAbstract,
                ],
                "constructor" => [],
                "properties" => [],
            ];

            $ref = $this->reflectClass($concrete);
            $ctor = $ref->getConstructor();

            if ($ctor) {
                foreach ($ctor->getParameters() as $param) {
                    $paramType = $param->getType();
                    if ($paramType instanceof ReflectionNamedType && !$paramType->isBuiltin()) {
                        $dep = $paramType->getName();
                        $graph["constructor"][$param->getName()] = [
                            "dependency" => $dep,
                            "mode" => ($this->bindings[$dep]["mode"] ?? BindingMode::Scoped)->value,
                            "singleton" => ($this->bindings[$dep]["mode"] ?? BindingMode::Scoped) === BindingMode::Singleton,
                            "lazy" => $this->bindings[$dep]["lazy"] ?? false,
                            "scope" => $this->bindings[$dep]["scope"] ?? $scope,
                            "tags" => [
                                TagScope::Universal->value => $this->collectTags($dep, TagScope::Universal),
                                TagScope::Scoped->value => $this->collectTags($dep, TagScope::Scoped, $scope),
                            ],
                            "subgraph" => $this->getFullDependencyGraph($dep, $scope, $visited),
                        ];
                    }
                }
            }

            $props = $ref->getProperties(ReflectionProperty::IS_PUBLIC);
            if ($this->injectProtected) {
                $props = array_merge($props, $ref->getProperties(ReflectionProperty::IS_PROTECTED));
            }

            foreach ($props as $prop) {
                $attributes = $prop->getAttributes(InjectAttribute::class);

                if ($this->injectableOnlyWithAttributes && empty($attributes)) {
                    continue;
                }

                $serviceClass = null;

                if (!empty($attributes)) {
                    $serviceClass = $attributes[0]->newInstance()->service ?? null;
                }

                if (!$serviceClass) {
                    $type = $prop->getType();
                    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                        $serviceClass = $type->getName();
                    } else {
                        continue;
                    }
                }

                $graph["properties"][$prop->getName()] = [
                    "dependency" => $serviceClass,
                    "mode" => ($this->bindings[$serviceClass]["mode"] ?? BindingMode::Scoped)->value,
                    "singleton" => ($this->bindings[$serviceClass]["mode"] ?? BindingMode::Scoped) === BindingMode::Singleton,
                    "lazy" => $this->bindings[$serviceClass]["lazy"] ?? false,
                    "scope" => $this->bindings[$serviceClass]["scope"] ?? $scope,
                    "tags" => [
                        TagScope::Universal->value => $this->collectTags($serviceClass, TagScope::Universal),
                        TagScope::Scoped->value => $this->collectTags($serviceClass, TagScope::Scoped, $scope),
                    ],
                    "subgraph" => $this->getFullDependencyGraph($serviceClass, $scope, $visited),
                ];
            }

            return $graph;
        }

        /**
         * Returns a GraphAnalyser scoped to the given dependency graph.
         * @param string|null $scope Scope name to analyse; null uses the global dependency graph.
         * @return GraphAnalyser
         */
        public function getGraphAnalyser (?string $scope = null) : GraphAnalyser {
            $graph = $scope !== null
                ? ($this->scopedDependencyGraph[$scope] ?? [])
                : $this->dependencyGraph;

            return new GraphAnalyser($graph);
        }

        /**
         * Returns the dependency graph for a specific scope.
         * @param string $scope The scope name.
         * @return array<string, string[]> Map of class to dependencies for the given scope.
         */
        public function getScopedDependencyGraph (string $scope) : array {
            return $this->scopedDependencyGraph[$scope] ?? [];
        }

        /**
         * Returns whether the container can produce an entry for the given identifier.
         * @param string $abstract Identifier of the entry to look up.
         * @return bool Whether the container can return an entry for the given identifier.
         */
        public function has (string $abstract) : bool {
            $abstract = $this->aliases[$abstract] ?? $abstract;

            for ($i = count($this->scopeStack) - 1; $i >= 0; $i--) {
                if (isset($this->scopeInstances[$this->scopeStack[$i]][$abstract])) {
                    return true;
                }
            }

            return isset($this->instances[$abstract])
                || isset($this->bindings[$abstract])
                || isset($this->factories[$abstract])
                || class_exists($abstract);
        }

        /**
         * Returns whether the given abstract has an explicit registration in the container.
         * Unlike has(), unregistered but autoloadable class names return false, making this
         * a reliable guard for strict-mode callers.
         * @param string $abstract The abstract identifier to check.
         * @return bool Whether the abstract has an explicit registration.
         */
        public function hasBinding (string $abstract) : bool {
            $abstract = $this->aliases[$abstract] ?? $abstract;

            for ($i = count($this->scopeStack) - 1; $i >= 0; $i--) {
                if (isset($this->scopeInstances[$this->scopeStack[$i]][$abstract])) {
                    return true;
                }
            }

            return isset($this->instances[$abstract])
                || isset($this->bindings[$abstract])
                || isset($this->factories[$abstract]);
        }

        /**
         * Imports a container state snapshot previously written by exportCache(), restoring
         * bindings, aliases, injection maps, and attribute cache entries without re-parsing
         * configuration files or repeating reflection-based attribute scanning.
         * @param string $path Absolute path of the cache file to import.
         * @throws RuntimeException If the cache file does not exist or does not return an array.
         */
        public function loadCache (string $path) : static {
            if (!is_file($path)) {
                throw new ContainerException("Container cache file not found: '$path'.");
            }

            $state = require $path;

            if (!is_array($state)) {
                throw new ContainerException("Container cache file '$path' did not return an array.");
            }

            $this->aliases = array_merge($this->aliases, $state["aliases"] ?? []);
            $this->attributeCache = array_merge($this->attributeCache, $state["attributeCache"] ?? []);
            $this->bindings = array_merge($this->bindings, $state["bindings"] ?? []);
            $this->classParameters = array_merge($this->classParameters, $state["classParameters"] ?? []);
            $this->constructorInjectionMap = array_merge($this->constructorInjectionMap, $state["constructorInjectionMap"] ?? []);
            $this->propertyInjectionMap = array_merge($this->propertyInjectionMap, $state["propertyInjectionMap"] ?? []);

            return $this;
        }

        /**
         * Loads a declarative JSON configuration file into the container.
         *
         * Delegates all parsing and container API calls to ConfigLoader, keeping
         * the container focused on service resolution.
         * @param string $jsonFile Absolute or relative path to the JSON configuration file.
         * @throws RuntimeException If the file is missing or contains invalid JSON.
         */
        public function loadConfig (string $jsonFile) : static {
            (new ConfigLoader($this))->load($jsonFile);
            return $this;
        }

        /**
         * Creates a fresh instance of the given abstract, bypassing any stored singleton or scope
         * cache. Optional constructor parameter overrides are applied for this resolution only
         * and do not affect subsequent calls to get() or make().
         * @param string $abstract The abstract identifier to resolve.
         * @param array $params Named constructor parameter overrides for this resolution.
         * @throws RuntimeException If the abstract cannot be resolved.
         * @return mixed A freshly constructed, fully decorated instance.
         */
        public function make (string $abstract, array $params = []) : mixed {
            $abstract = $this->aliases[$abstract] ?? $abstract;
            $this->notifyBeforeResolving($abstract);
            $concrete = $this->bindings[$abstract]["concrete"] ?? $abstract;

            if (empty($this->bindings[$abstract]) && $this->strict) {
                throw new NotFoundException("Strict mode: '$abstract' has no explicit binding registered.");
            }

            if (empty($this->bindings[$abstract]) && isset($this->factories[$abstract])) {
                return $this->build($abstract, $this->factories[$abstract]);
            }

            if (!empty($params) && is_string($concrete)) {
                $prev = $this->classParameters[$concrete] ?? null;
                $this->classParameters[$concrete] = array_merge($prev ?? [], $params);
            }

            try {
                return $this->build($abstract, $concrete);
            }
            finally {
                if (!empty($params) && is_string($concrete)) {
                    if ($prev === null) {
                        unset($this->classParameters[$concrete]);
                    }
                    else {
                        $this->classParameters[$concrete] = $prev;
                    }
                }
            }
        }

        /**
         * Registers a PHP-side callback to be invoked whenever an abstract is bound or rebound.
         * Pass '*' as the abstract to observe every bind() call.
         * @param string $abstract The abstract identifier to observe, or '*' for all bindings.
         * @param callable $callback A callable receiving the abstract, its concrete, and the container.
         */
        public function onBind (string $abstract, callable $callback) : static {
            $this->bindCallbacks[$abstract][] = $callback;
            return $this;
        }

        /**
         * Registers a callback to be invoked after a service has been fully resolved and all
         * extenders applied. Pass '*' as the abstract to hook every resolution.
         * @param string $abstract The abstract class or interface to hook, or '*' for all resolutions.
         * @param callable $callback A callable receiving the resolved instance and the container.
         */
        public function onResolved (string $abstract, callable $callback) : static {
            $this->resolvedCallbacks[$abstract][] = $callback;
            return $this;
        }

        /**
         * Registers a callback to be invoked immediately after a service is constructed,
         * before extenders are applied. Pass '*' as the abstract to hook every resolution.
         * @param string $abstract The abstract class or interface to hook, or '*' for all resolutions.
         * @param callable $callback A callable receiving the constructed instance and the container.
         */
        public function onResolving (string $abstract, callable $callback) : static {
            $this->resolvingCallbacks[$abstract][] = $callback;
            return $this;
        }

        /**
         * Prints a human-readable representation of the dependency graph to stdout.
         * @param string|null $scope Scope to print; null prints the global graph.
         */
        public function printDependencyGraph (?string $scope = null) : static {
            $this->getGraphAnalyser($scope)->printGraph();
            return $this;
        }

        /**
         * Removes an existing binding and all its cached instances, then re-registers it with the
         * new concrete and options. Equivalent to calling forget() followed by bind().
         * @param string $abstract The abstract identifier to rebind.
         * @param string|callable $concrete The new concrete class name or factory callable.
         * @param array $options Optional binding options (singleton, lazy, scope, tags).
         */
        public function rebind (string $abstract, string|callable $concrete, array $options = []) : static {
            $this->forget($abstract);
            $this->bind($abstract, $concrete, $options);
            return $this;
        }

        /**
         * Registers a factory callable for the given service identifier.
         *
         * Registered factories are consulted by get(), make(), and Invoker instances during
         * parameter resolution. A factory-backed identifier takes precedence over bare
         * autowiring but yields to an explicit bind().
         * @param string $id The service identifier.
         * @param mixed $factory The factory callable, Factory instance, or class name.
         */
        public function registerFactory (string $id, mixed $factory) : static {
            $this->factories[$id] = $factory;
            return $this;
        }

        /**
         * Registers a named scope with the container, initialising its instance cache.
         * @param string $scopeName The scope name.
         */
        public function registerScope (string $scopeName) : static {
            if (!isset($this->scopeInstances[$scopeName])) {
                $this->scopeInstances[$scopeName] = [];
            }

            return $this;
        }

        /**
         * Registers a contextual binding override directly.
         * @param string $consumer The consumer class FQCN.
         * @param string $needs The abstract or interface the consumer depends on.
         * @param string|callable|object $concrete The concrete to inject instead of the default.
         * @return static
         */
        public function setContextualBinding (string $consumer, string $needs, string|callable|object $concrete) : static {
            $this->contextual[$consumer][$needs] = $concrete;
            return $this;
        }

        /**
         * Controls whether property injection is attempted only on properties marked with #[Inject].
         * @param bool $value Whether to require the #[Inject] attribute for property injection.
         */
        public function setInjectableOnlyWithAttributes (bool $value) : static {
            $this->injectableOnlyWithAttributes = $value;
            return $this;
        }

        /**
         * Controls whether protected properties are eligible for automatic injection.
         * @param bool $value Whether to inject protected properties in addition to public ones.
         */
        public function setInjectProtected (bool $value) : static {
            $this->injectProtected = $value;
            return $this;
        }

        /**
         * Sets a global scalar parameter value available as a fallback to all constructor resolutions.
         * @param string $param The constructor parameter name.
         * @param mixed $value The value to inject.
         */
        public function setParameter (string $param, mixed $value) : static {
            $this->parameters[$param] = $value;
            return $this;
        }

        /**
         * Enables or disables strict mode.
         *
         * When strict mode is on, any call to get() for an abstract with no explicit binding
         * throws a RuntimeException instead of attempting to autowire the class. This prevents
         * accidental resolution of unregistered concretes in large codebases.
         * @param bool $value Whether strict mode should be enabled.
         */
        public function setStrict (bool $value) : static {
            $this->strict = $value;
            return $this;
        }

        /**
         * Registers a tag for an abstract, making it discoverable via getByTag().
         * @param string $tagName The tag name.
         * @param string $abstract The service abstract to tag.
         * @param int $priority Higher-priority services are returned earlier by getByTag().
         */
        public function tag (string $tagName, string $abstract, int $priority = 0) : static {
            $scope = $this->getCurrentScope();
            $entry = ["abstract" => $abstract, "priority" => $priority];
            $this->tags[$tagName][$abstract] = $entry;
            $this->scopeTags[$scope][$tagName][$abstract] = $entry;

            return $this;
        }

        /**
         * Pre-populates the reflection cache and runs attribute scanning for all concrete classes
         * currently registered as bindings. Call once during application warmup to front-load all
         * reflection work, eliminating per-request overhead on the first resolution of each service.
         */
        public function warmup () : static {
            foreach ($this->bindings as $binding) {
                $concrete = $binding["concrete"] ?? null;

                if (!is_string($concrete) || !class_exists($concrete)) {
                    continue;
                }

                $this->reflectClass($concrete);

                if (!isset($this->attributeCache[$concrete])) {
                    $this->attributeCache[$concrete] = true;
                    $this->registerAttributes($concrete);
                }
            }

            return $this;
        }
    }
?>