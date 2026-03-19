<?php
    /**
     * Project Name:    Wingman Synapse - Provider
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
    use Wingman\Synapse\Interfaces\ProviderInterface;

    /**
     * Base class for enterprise service providers.
     *
     * Providers participate in a two-phase lifecycle:
     *
     * 1. register() — declare bindings, aliases, and factories.
     * 2. boot() — run logic that depends on all providers being registered.
     *
     * Providers may also be marked as deferred by setting `$deferred = true`
     * and declaring the keys they provide in `$provides`.
     *
     * @package Wingman\Synapse
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    abstract class Provider implements ProviderInterface {
        /**
         * The container used by this provider.
         * @var Container
         */
        protected Container $container;

        /**
         * Whether this provider should be registered lazily.
         * @var bool
         */
        protected bool $deferred = false;

        /**
         * Provider class dependencies that must run before this provider.
         * @var class-string[]
         */
        protected array $dependsOn = [];

        /**
         * Priority used by provider runners when ordering providers.
         * Higher values run earlier.
         * @var int
         */
        protected int $priority = 0;

        /**
         * Service identifiers provided by this provider.
         * Relevant only when `$deferred` is true.
         * @var string[]
         */
        protected array $provides = [];

        /**
         * Optional provider class dependencies that are respected only when present.
         * Unlike $dependsOn, a missing soft dependency is silently ignored.
         * @var class-string[]
         */
        protected array $softDependsOn = [];

        /**
         * Creates a new provider.
         * @param Container $container The application container.
         */
        public function __construct (Container $container) {
            $this->container = $container;
        }

        /**
         * Merges default provider configuration with user overrides.
         * @template T of array
         * @param T $defaults Provider defaults.
         * @param array $overrides User overrides.
         * @return T The merged configuration.
         */
        protected function mergeConfig (array $defaults, array $overrides) : array {
            return array_replace_recursive($defaults, $overrides);
        }

        /**
         * Convenience wrapper around Container::onResolved().
         * @param string $abstract The abstract to observe.
         * @param callable $callback Callback receiving the resolved instance and container.
         */
        protected function onResolved (string $abstract, callable $callback) : void {
            $this->container->onResolved($abstract, $callback);
        }

        /**
         * Boots the provider after all providers are registered.
         */
        public function boot () : void {}

        /**
         * Returns the container instance.
         * @return Container The application container.
         */
        public function getContainer () : Container {
            return $this->container;
        }

        /**
         * Returns provider dependencies that must be registered/booted first.
         * @return class-string[] The provider class names this provider depends on.
         */
        public function getDependencies () : array {
            return $this->dependsOn;
        }

        /**
         * Returns optional provider dependencies that are respected only when present.
         * @return class-string[] The optional provider class names this provider depends on.
         */
        public function getSoftDependencies () : array {
            return $this->softDependsOn;
        }

        /**
         * Returns the provider execution priority.
         * @return int The provider priority.
         */
        public function getPriority () : int {
            return $this->priority;
        }

        /**
         * Returns the service identifiers provided by this provider.
         * @return string[] A list of provided service identifiers.
         */
        public function getProvidedAbstracts () : array {
            return $this->provides;
        }

        /**
         * Returns whether the provider is deferred.
         * @return bool Whether the provider is deferred.
         */
        public function isDeferred () : bool {
            return $this->deferred;
        }

        /**
         * Registers services into the container.
         */
        public function register () : void {}
    }
?>