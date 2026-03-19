<?php
    /**
     * Project Name:    Wingman Synapse - Cortex Configuration Bridge
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse.Bridge.Cortex namespace.
    namespace Wingman\Synapse\Bridge\Cortex;

    # Guard against double-inclusion (e.g. via symlinked paths resolving to different strings
    # under require_once). If the alias or stub is already in place there is nothing to do.
    if (class_exists(__NAMESPACE__ . '\\Configuration', false)) return;

    # If Cortex is installed, alias the Cortex Configuration class to this namespace.
    if (class_exists(\Wingman\Cortex\Configuration::class)) {
        class_alias(\Wingman\Cortex\Configuration::class, __NAMESPACE__ . '\\Configuration');
        return;
    }

    # Import the following classes to the current scope.
    use ReflectionNamedType;
    use ReflectionObject;

    /**
     * A no-op stub used when Cortex is not available.
     * Mirrors the static and instance API surface of Wingman Cortex Configuration used by Synapse.
     * @package Wingman\Synapse\Bridge\Cortex
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Configuration {
        /**
         * Stored key-value pairs used by the stub hydrate flow.
         * @var array<string, mixed>
         */
        private array $data = [];

        /**
         * Returns null; Cortex is unavailable so no named configuration can be found.
         * @param string|null $name Ignored.
         * @return static|null Always null.
         */
        public static function find (?string $name = null) : ?static {
            return null;
        }

        /**
         * Returns false; Cortex is unavailable so no named configuration can exist.
         * @param string|null $name Ignored.
         * @return bool Always false.
         */
        public static function exists (?string $name = null) : bool {
            return false;
        }

        /**
         * Returns an empty array; Cortex is unavailable so there are no registered configurations.
         * @return array Always an empty array.
         */
        public static function getAll () : array {
            return [];
        }

        /**
         * Returns an empty array; Cortex is unavailable so there are no registered configurations.
         * @return array Always an empty array.
         */
        public static function getAllNames () : array {
            return [];
        }

        /**
         * Returns a new stub instance; Cortex is unavailable so no data is actually stored.
         * @param iterable $data Ignored.
         * @param string|null $name Ignored.
         * @return static A fresh stub instance.
         */
        public static function fromIterable (iterable $data, ?string $name = null) : static {
            $configuration = new static();

            foreach ($data as $key => $value) {
                if (is_string($key)) {
                    $configuration->data[$key] = $value;
                }
            }

            return $configuration;
        }

        /**
         * Hydrates #[Configurable]-annotated properties of target from a flat dot-notation array.
         * When source is already a stub instance it is returned as-is.
         * @param object $target The object whose properties should be hydrated.
         * @param array|self $source A flat dot-notation key-value array, or a stub instance.
         * @param array $map Ignored in the stub; present for API compatibility.
         * @param bool $strict Ignored in the stub; present for API compatibility.
         * @return self A stub instance.
         */
        public static function hydrate (object $target, array|self $source = [], array $map = [], bool $strict = false) : self {
            if ($source instanceof self) {
                $source = $source->data;
            }

            if (is_array($source) && !empty($source)) {
                $reflection = new ReflectionObject($target);

                foreach ($reflection->getProperties() as $property) {
                    foreach ($property->getAttributes() as $attribute) {
                        if ($attribute->getName() !== \Wingman\Synapse\Bridge\Cortex\Attributes\Configurable::class) continue;

                        $key = $attribute->newInstance()->getKey();

                        if (!array_key_exists($key, $source)) continue;

                        $value = $source[$key];
                        $type = $property->getType();

                        if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
                            $value = match ($type->getName()) {
                                "bool" => (bool) $value,
                                "int" => (int) $value,
                                "float" => (float) $value,
                                "string" => (string) $value,
                                default => $value,
                            };
                        }

                        $property->setValue($target, $value);
                    }
                }
            }

            return $source instanceof self ? $source : new static();
        }

        /**
         * Returns null; Cortex is unavailable so the configuration has no name.
         * @return string|null Always null.
         */
        public function getName () : ?string {
            return null;
        }

        /**
         * No-op; Cortex is unavailable so object state cannot be captured.
         * @param object $object Ignored.
         * @param string $name Ignored.
         * @return static The stub, for fluent chaining.
         */
        public function captureObject (object $object, string $name) : static {
            return $this;
        }

        /**
         * No-op; Cortex is unavailable so object state cannot be restored.
         * @param object $object Ignored.
         * @param string $name Ignored.
         * @return static The stub, for fluent chaining.
         */
        public function restoreObject (object $object, string $name) : static {
            return $this;
        }
    }
?>