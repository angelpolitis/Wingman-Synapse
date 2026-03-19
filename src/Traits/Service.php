<?php
    /**
     * Project Name:    Wingman Synapse - Traits - Service
     * Created by:      Angel Politis
     * Creation Date:   Nov 28 2025
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse.Traits namespace.
    namespace Wingman\Synapse\Traits;

    /**
     * Default implementation for classes that self-register as container services.
     *
     * Using this trait satisfies the Interfaces\Service contract with sensible defaults:
     * singleton behaviour is enabled and no specific scope is declared. Both values may
     * be overridden by redefining the protected properties in the using class.
     *
     * @package Wingman\Synapse\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait Service {
        /**
         * Whether this service should be treated as a singleton by the container.
         * @var bool
         */
        protected bool $singleton = true;

        /**
         * The scope this service belongs to, or null for the global scope.
         * @var string|null
         */
        protected ?string $scope = null;

        /**
         * Whether the container should treat this service as a singleton.
         * @return bool Whether this service is a singleton.
         */
        public function isSingleton () : bool {
            return $this->singleton;
        }

        /**
         * The scope this service belongs to.
         * @return string|null The scope name, or `null` for the global scope.
         */
        public function getScope () : ?string {
            return $this->scope;
        }
    }
?>