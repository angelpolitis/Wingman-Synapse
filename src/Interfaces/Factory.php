<?php
    /**
     * Project Name:    Wingman Synapse - Interfaces - Factory
     * Created by:      Angel Politis
     * Creation Date:   Nov 28 2025
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse.Interfaces namespace.
    namespace Wingman\Synapse\Interfaces;

    # Import the following classes to the current scope.
    use Wingman\Synapse\Container;

    /**
     * Contract for factory objects that create services on behalf of the container.
     *
     * A factory encapsulates custom instantiation logic for a particular service identifier,
     * receiving the container and the requested id so it can resolve its own dependencies
     * as needed.
     *
     * @package Wingman\Synapse\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface Factory {
        /**
         * Create and return an object for the given service identifier.
         * @param Container $container The container requesting the object.
         * @param string $id The service identifier being resolved.
         * @return object The created object.
         */
        public function create (Container $container, string $id) : object;
    }
?>