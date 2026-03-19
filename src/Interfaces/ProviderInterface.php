<?php
    /**
     * Project Name:    Wingman Synapse - Interfaces - Provider Interface
     * Created by:      Angel Politis
     * Creation Date:   Mar 18 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse.Interfaces namespace.
    namespace Wingman\Synapse\Interfaces;

    use Wingman\Synapse\Container;

    /**
     * Defines the contract for service providers consumed by ProviderManager.
     *
     * Lifecycle contract:
     *
     * - register() is called exactly once per provider instance and always before boot().
     * - boot() is called exactly once per provider instance after all non-deferred providers
     *   have completed register().
     * - Deferred providers are registered and booted on first resolution of any abstract
     *   returned by getProvidedAbstracts().
     *
     * @package Wingman\Synapse\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface ProviderInterface {
        /**
         * Boots the provider after the registration phase has completed.
         */
        public function boot () : void;

        /**
         * Returns the provider container.
         * @return Container The container instance.
         */
        public function getContainer () : Container;

        /**
         * Returns class names of providers this provider depends on.
         * @return class-string[] Provider class names.
         */
        public function getDependencies () : array;

        /**
         * Returns class names of optional providers this provider should run after, when present.
         * A listed provider that is not registered is silently ignored.
         * @return class-string[] Provider class names.
         */
        public function getSoftDependencies () : array;

        /**
         * Returns provider execution priority for tie-breaking.
         * Higher numbers are processed first when dependency order permits.
         * @return int The provider priority.
         */
        public function getPriority () : int;

        /**
         * Returns abstract identifiers served by this provider when deferred.
         * @return string[] Abstract identifiers.
         */
        public function getProvidedAbstracts () : array;

        /**
         * Returns whether this provider is deferred.
         * @return bool Whether this provider is deferred.
         */
        public function isDeferred () : bool;

        /**
         * Registers bindings into the container.
         */
        public function register () : void;
    }
?>