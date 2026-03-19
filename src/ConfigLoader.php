<?php
    /**
     * Project Name:    Wingman Synapse - Config Loader
     * Created by:      Angel Politis
     * Creation Date:   Mar 18 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse namespace.
    namespace Wingman\Synapse;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Wingman\Synapse\Enums\BindingMode;

    /**
     * Loads a declarative JSON configuration file into the container.
     *
     * ConfigLoader is responsible for parsing a JSON manifest and translating
     * each configuration section into the appropriate container API calls, keeping
     * the Container class focused solely on service resolution.
     *
     * Supported top-level keys: scopes, services, tags, contextual, parameters, aliases.
     *
     * @package Wingman\Synapse
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ConfigLoader {
        /**
         * The container to configure.
         * @var Container
         */
        private Container $container;

        /**
         * Creates a new configuration loader.
         * @param Container $container The container to configure.
         */
        public function __construct (Container $container) {
            $this->container = $container;
        }

        /**
         * Registers abstract aliases.
         * @param array<string, string> $aliases
         */
        private function loadAliases (array $aliases) : void {
            foreach ($aliases as $alias => $target) {
                $this->container->alias($alias, $target);
            }
        }

        /**
         * Registers contextual binding overrides.
         * @param array<string, array<string, string>> $contextual
         */
        private function loadContextual (array $contextual) : void {
            foreach ($contextual as $consumer => $overrides) {
                foreach ($overrides as $needs => $concrete) {
                    $this->container->setContextualBinding($consumer, $needs, $concrete);
                }
            }
        }

        /**
         * Registers scalar constructor parameter values per class.
         * @param array<string, array<string, mixed>> $parameters
         */
        private function loadParameters (array $parameters) : void {
            foreach ($parameters as $class => $params) {
                foreach ($params as $param => $value) {
                    $this->container->addParameter($class, $param, $value);
                }
            }
        }

        /**
         * Registers all declared scopes with the container.
         * @param string[] $scopes
         */
        private function loadScopes (array $scopes) : void {
            foreach ($scopes as $scope) {
                $this->container->registerScope($scope);
            }
        }

        /**
         * Binds all declared services, including optional injection maps.
         * @param array<string, array<string, mixed>> $services
         */
        private function loadServices (array $services) : void {
            foreach ($services as $abstract => $config) {
                $mode = $config["mode"] ?? (($config["singleton"] ?? false)
                    ? BindingMode::Singleton
                    : BindingMode::Scoped);

                $this->container->bind($abstract, $config["concrete"] ?? $abstract, [
                    "mode" => BindingMode::resolve($mode),
                    "lazy" => $config["lazy"] ?? false,
                    "scope" => $config["scope"] ?? null,
                    "tags" => $config["tags"] ?? [],
                ]);

                foreach ($config["constructorInjection"] ?? [] as $param => $inject) {
                    $this->container->addConstructorInjection(
                        abstract: $abstract,
                        param: $param,
                        service: $inject["service"] ?? null,
                        tag: $inject["tag"] ?? null,
                    );
                }

                foreach ($config["propertyInjection"] ?? [] as $property => $inject) {
                    $this->container->addPropertyInjection(
                        abstract: $abstract,
                        property: $property,
                        service: $inject["service"] ?? null,
                        tag: $inject["tag"] ?? null,
                    );
                }
            }
        }

        /**
         * Registers standalone tag associations (outside of service declarations).
         *
         * Accepts two JSON shapes per tag:
         * - A flat list `["FqcnA", "FqcnB"]` — all registered with priority 0.
         * - A priority map `{ "FqcnA": 10, "FqcnB": 5 }` — each registered with its declared priority.
         * @param array<string, string[]|array<string, int>> $tags
         */
        private function loadTags (array $tags) : void {
            foreach ($tags as $tagName => $abstracts) {
                if (array_is_list($abstracts)) {
                    foreach ($abstracts as $abstract) {
                        $this->container->tag($tagName, $abstract);
                    }
                }
                else {
                    foreach ($abstracts as $abstract => $priority) {
                        $this->container->tag($tagName, $abstract, (int) $priority);
                    }
                }
            }
        }

        /**
         * Parses and applies the given JSON configuration file to the container.
         * @param string $jsonFile Absolute or relative path to the JSON file.
         * @throws RuntimeException If the file does not exist or contains invalid JSON.
         */
        public function load (string $jsonFile) : void {
            if (!file_exists($jsonFile)) {
                throw new RuntimeException("Config file '$jsonFile' not found.");
            }

            $json = file_get_contents($jsonFile);
            $config = json_decode($json, true);

            if (!is_array($config)) {
                throw new RuntimeException("Invalid JSON in '$jsonFile': " . json_last_error_msg() . ".");
            }

            $this->loadScopes($config["scopes"] ?? []);
            $this->loadServices($config["services"] ?? []);
            $this->loadTags($config["tags"] ?? []);
            $this->loadContextual($config["contextual"] ?? []);
            $this->loadParameters($config["parameters"] ?? []);
            $this->loadAliases($config["aliases"] ?? []);
        }
    }
?>