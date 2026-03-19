<?php
    /**
     * Project Name:    Wingman Synapse - Interfaces - Service
     * Created by:      Angel Politis
     * Creation Date:   Nov 28 2025
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse.Interfaces namespace.
    namespace Wingman\Synapse\Interfaces;

    /**
     * Contract for classes that self-register as container services.
     *
     * Implementing this interface, or using the companion Traits\Service trait,
     * signals the container to register the class as a managed binding automatically
     * without requiring a matching #[Service] attribute or an explicit bind() call.
     *
     * @package Wingman\Synapse\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface Service {
        /**
         * Whether the container should treat this service as a singleton.
         * @return bool Whether a single shared instance should be maintained.
         */
        public function isSingleton () : bool;

        /**
         * The scope this service belongs to, or null for the global scope.
         * @return string|null The scope name, or `null`.
         */
        public function getScope () : ?string;
    }
?>