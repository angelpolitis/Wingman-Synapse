<?php
    /**
     * Project Name:    Wingman Synapse - Interfaces - Context
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

    /**
     * Contract for classes that declare a contextual binding rule.
     *
     * A class implementing this interface tells the container: "when $consumer needs
     * $needs, inject me instead of the default binding."
     *
     * @package Wingman\Synapse\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface Context {
        /**
         * The consumer class that triggers this contextual override.
         * @return string Fully-qualified consumer class name.
         */
        public function getConsumer () : string;

        /**
         * The abstract/interface the consumer is requesting.
         * @return string Fully-qualified abstract or interface name.
         */
        public function getNeeds () : string;
    }
?>