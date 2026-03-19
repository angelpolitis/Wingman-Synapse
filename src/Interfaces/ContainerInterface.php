<?php
    /**
     * Project Name:    Wingman Synapse - Interfaces - Container Interface
     * Created by:      Angel Politis
     * Creation Date:   Mar 18 2026
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse.Interfaces namespace.
    namespace Wingman\Synapse\Interfaces;

    # Import the following classes to the current scope.
    use RuntimeException;

    /**
     * Defines the minimal service-locator contract for a Synapse container.
     *
     * This interface mirrors the PSR-11 ContainerInterface specification exactly so
     * that Wingman\Synapse\Bridge\PSR\ContainerInterface can alias it when the
     * psr/container package is not present in the project. Container implements the
     * bridge, not this interface directly, ensuring PSR-11 interoperability is
     * transparent to the consumer.
     *
     * @package Wingman\Synapse\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface ContainerInterface {
        /**
         * Finds an entry in the container by its identifier and returns it.
         * @param string $id Identifier of the entry to look up.
         * @throws RuntimeException If the identifier cannot be resolved.
         * @return mixed The resolved entry.
         */
        public function get (string $id) : mixed;

        /**
         * Returns whether the container can produce an entry for the given identifier.
         * @param string $id Identifier of the entry to look up.
         * @return bool Whether the container can return an entry for the given identifier.
         */
        public function has (string $id) : bool;
    }
?>