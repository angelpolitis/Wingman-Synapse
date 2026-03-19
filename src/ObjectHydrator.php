<?php
    /**
     * Project Name:    Wingman Synapse - Object Hydrator
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
    use ReflectionClass;
    use ReflectionNamedType;
    use ReflectionProperty;
    use RuntimeException;

    /**
     * A utility for mapping associative arrays to typed objects (Data Transfer Objects).
     *
     * This hydrator is intentionally decoupled from dependency injection. It does not resolve
     * services through the container — it maps plain data arrays to typed value objects.
     *
     * For constructor-based DTOs (readonly/immutable), it resolves constructor parameters by
     * name from the data array and recurses into nested typed DTOs automatically. For
     * property-based DTOs (no constructor), it assigns all public properties present in the
     * data array directly.
     *
     * @package Wingman\Synapse
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ObjectHydrator {

        /**
         * Hydrate a single typed object from an associative data array.
         *
         * Constructor injection is attempted first (supporting immutable/readonly DTOs). If no
         * constructor is present, public properties are assigned directly.
         *
         * @template T of object
         * @param class-string<T> $class The fully-qualified class name to hydrate into.
         * @param array<string, mixed> $data The associative data array.
         * @return T The hydrated object.
         */
        public function hydrate (string $class, array $data) : object {
            $ref = new ReflectionClass($class);

            if ($constructor = $ref->getConstructor()) {
                $params = [];

                foreach ($constructor->getParameters() as $param) {
                    $name = $param->getName();
                    $type = $param->getType();

                    $isNestedDto = $type instanceof ReflectionNamedType
                        && !$type->isBuiltin()
                        && isset($data[$name])
                        && is_array($data[$name]);

                    if ($isNestedDto) {
                        $params[] = $this->hydrate($type->getName(), $data[$name]);
                    }
                    elseif (array_key_exists($name, $data)) {
                        $params[] = $data[$name];
                    }
                    elseif ($param->isOptional()) {
                        $params[] = $param->getDefaultValue();
                    }
                    else {
                        throw new RuntimeException("Cannot hydrate $class: required constructor parameter '\$$name' is missing from the provided data.");
                    }
                }

                return $ref->newInstanceArgs($params);
            }

            $obj = $ref->newInstanceWithoutConstructor();

            foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
                $name = $prop->getName();
                if (array_key_exists($name, $data)) {
                    $prop->setValue($obj, $data[$name]);
                }
            }

            return $obj;
        }

        /**
         * Hydrate a list of typed objects from an array of associative data arrays.
         *
         * @template T of object
         * @param class-string<T> $class The fully-qualified class name to hydrate into.
         * @param array<int, array<string, mixed>> $dataSet The array of data arrays.
         * @return T[] The array of hydrated objects.
         */
        public function hydrateMany (string $class, array $dataSet) : array {
            return array_map(fn (array $data) => $this->hydrate($class, $data), $dataSet);
        }
    }
?>