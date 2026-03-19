<?php
    /**
     * Project Name:    Wingman Synapse - Invoker
     * Created by:      Angel Politis
     * Creation Date:   Nov 28 2025
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse namespace.
    namespace Wingman\Synapse;

    # Import the following classes to the current scope.
    use Wingman\Synapse\Attributes\Inject as InjectAttribute;
    use Wingman\Synapse\Interfaces\Factory;
    use Exception;
    use InvalidArgumentException;
    use ReflectionFunction;
    use ReflectionMethod;
    use ReflectionNamedType;
    use RuntimeException;

    /**
     * Represents an Invoker.
     * @package Wingman\Synapse
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Invoker {
        /**
         * The container that spawned an invoker.
         * @var Container
         */
        protected Container $container;

        /**
         * The main parameters of an invoker.
         * @var array
         */
        protected array $params = [];

        /**
         * The extra parameters of an invoker (less important).
         * @var array
         */
        protected array $extraParams = [];

        /**
         * The external objects loaded into an invoker.
         * @var object[]
         */
        protected array $objects = [];

        /**
         * The scope of an invoker.
         * @var ?string
         */
        protected ?string $scope = null;

        /**
         * The factory used by an invoker to create objects.
         * @var Factory|callable|string|null
         */
        protected mixed $factory = null;

        /**
         * Creates a new invoker.
         * @param Container $container The container that spawned the invoker.
         */
        public function __construct (Container $container) {
            $this->container = $container;
        }

        /**
         * Resolves the callable and determines the target object when needed.
         * @param object|array|string|callable $context The callable target or class/object context.
         * @param string|null $method Optional method name when context is a class name or object.
         * @throws RuntimeException If the context cannot be resolved to a callable.
         * @throws InvalidArgumentException If the callable context is invalid.
         * @return array{0: \ReflectionFunctionAbstract, 1: ?object}
         */
        protected function resolveCallable (object|array|string|callable $context, ?string $method = null) : array {
            if (is_string($context) && $method !== null) {
                $ref = new ReflectionMethod($context, $method);
                $target = $ref->isStatic() ? null : $this->container->get($context);
                return [$ref, $target];
            }

            if (is_object($context) && $method !== null) {
                return [new ReflectionMethod($context, $method), $context];
            }

            if (is_callable($context) && !is_array($context)) {
                return [new ReflectionFunction($context), null];
            }

            if (is_array($context) && count($context) === 2) {
                [$classOrObj, $method] = $context;
                $ref = new ReflectionMethod($classOrObj, $method);
                $target = is_object($classOrObj)
                    ? $classOrObj
                    : ($ref->isStatic() ? null : $this->container->get($classOrObj));
                return [$ref, $target];
            }

            if (is_string($context)) {
                if (function_exists($context)) {
                    return [new ReflectionFunction($context), null];
                }
                throw new RuntimeException("'$context' is neither a known function nor a class paired with a method name");
            }

            throw new InvalidArgumentException("Invalid callable context provided");
        }

        /**
         * Invokes the target callable, resolving all unspecified typed arguments from the container,
         * external objects, factories, and overridden parameter maps.
         * @param object|array|string|callable $context The callable target or class/object context.
         * @param string|null $method Optional method name when context is a class name or object.
         * @throws RuntimeException If a parameter cannot be resolved.
         * @return mixed The return value of the invoked callable.
         */
        public function call (object|array|string|callable $context, ?string $method = null) : mixed {
            $scope = $this->scope ?? $this->container->getDefaultScope();
            $alreadyInScope = $scope === $this->container->getDefaultScope()
                && $this->scope === null;

            if (!$alreadyInScope) {
                $this->container->enterScope($scope);
            }

            try {
                [$ref, $target] = $this->resolveCallable($context, $method);
                $params = [];

                foreach ($ref->getParameters() as $param) {
                    $name = $param->getName();
                    $type = $param->getType();

                    if (isset($this->params[$name])) {
                        $params[$name] = $this->params[$name];
                        continue;
                    }

                    if (isset($this->extraParams[$name])) {
                        $params[$name] = $this->extraParams[$name];
                        continue;
                    }

                    if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                        $className = $type->getName();

                        if (isset($this->objects[$className])) {
                            $params[$name] = $this->objects[$className];
                            continue;
                        }

                        $injectAttrs = $param->getAttributes(InjectAttribute::class);
                        if (!empty($injectAttrs)) {
                            $injectAttr = $injectAttrs[0]->newInstance();

                            if ($injectAttr->tag !== null) {
                                $params[$name] = $this->container->getByTag($injectAttr->tag, scope: $this->scope);
                                continue;
                            }

                            if ($injectAttr->service !== null) {
                                $className = $injectAttr->service;
                            }
                        }

                        $factoryToUse = $this->factory ?? $this->container->getFactoryFor($className);

                        if ($factoryToUse) {
                            if ($factoryToUse instanceof Factory) {
                                $params[$name] = $factoryToUse->create($this->container, $className);
                                continue;
                            }

                            if (is_callable($factoryToUse)) {
                                $params[$name] = $factoryToUse($this->container, $className);
                                continue;
                            }

                            if (is_string($factoryToUse)) {
                                $instance = $this->container->get($factoryToUse);
                                if (is_callable($instance)) {
                                    $params[$name] = $instance($this->container, $className);
                                    continue;
                                }
                            }

                            throw new RuntimeException("Invalid factory for $className");
                        }

                        $params[$name] = $this->container->get($className);
                    } elseif ($type instanceof ReflectionNamedType && $type->getName() === 'object' && !empty($this->objects)) {
                        $params[$name] = reset($this->objects);
                    } elseif ($param->isDefaultValueAvailable()) {
                        $params[$name] = $param->getDefaultValue();
                    } else {
                        $contextStr = is_array($context)
                            ? (is_object($context[0]) ? get_class($context[0]) : $context[0]) . "::$context[1]"
                            : (is_object($context) ? get_class($context) : (string)$context);

                        throw new RuntimeException("Cannot resolve parameter \$$name for $contextStr");
                    }
                }

                if ($ref instanceof ReflectionMethod) {
                    return $ref->invokeArgs($target, $params);
                }

                assert($ref instanceof ReflectionFunction);
                return $ref->invokeArgs($params);

            }
            finally {
                if (!$alreadyInScope) {
                    $this->container->exitScope();
                }
            }
        }

        /**
         * Merges a set of named parameters into the invoker's secondary parameter map.
         * These are checked after the primary map but before type-based container resolution.
         * Positional arrays are rejected; only associative arrays are accepted.
         * @param array $params An associative array of parameter name => value pairs.
         * @throws Exception If a positional (non-associative) array is provided.
         * @return static
         */
        public function useExtraParams (array $params) : static {
            if (empty($params)) {
                return $this;
            }
            if (array_is_list($params)) {
                throw new Exception("Positional arguments are not allowed in configuration. Use named arguments instead.");
            }
            $this->extraParams = array_merge($this->extraParams, $params);
            return $this;
        }

        /**
         * Sets the factory used to create object dependencies during call argument resolution.
         * Accepts a Factory instance, a callable, a class name (whose __invoke will be called),
         * or null to clear any previously registered factory.
         * @param Factory|callable|string|null $factory
         * @return static
         */
        public function useFactory (Factory|callable|string|null $factory) : static {
            $this->factory = $factory;
            return $this;
        }

        /**
         * Registers pre-built object instances that will be injected by class name during
         * call argument resolution, bypassing the container entirely. Array keys are ignored;
         * each object is keyed internally by its fully-qualified class name.
         * @param object[] $objects A list of object instances to register.
         * @return static
         */
        public function useObjects (array $objects) : static {
            foreach ($objects as $obj) {
                $this->objects[get_class($obj)] = $obj;
            }
            return $this;
        }

        /**
         * Merges a set of named parameters into the invoker's primary parameter map.
         * These are checked first when resolving call arguments, before the container
         * or the extra parameter map is consulted.
         * @param array $params An associative array of parameter name => value pairs.
         * @return static
         */
        public function useParams (array $params) : static {
            $this->params = array_merge($this->params, $params);
            return $this;
        }

        /**
         * Sets the scope the invoker will enter before invoking the target and exit afterwards.
         * Passing null causes the invoker to use the container's default global scope.
         * @param string|null $scope The scope name, or null for the default scope.
         * @return static
         */
        public function useScope (?string $scope) : static {
            $this->scope = $scope;
            return $this;
        }
    }
?>