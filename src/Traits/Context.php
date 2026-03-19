<?php
    /**
     * Project Name:    Wingman Synapse - Traits - Context
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
     * Provides the implementation glue for classes that declare a contextual binding rule
     * via code rather than via the #[Context] attribute.
     *
     * The using class must implement both getConsumer() and getNeeds(), satisfying the
     * Interfaces\Context contract.
     *
     * @package Wingman\Synapse\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait Context {
        /**
         * Return the consumer class that triggers this contextual override.
         * @return string Fully-qualified consumer class name.
         */
        abstract public function getConsumer () : string;

        /**
         * Return the abstract or interface the consumer is requesting.
         * @return string Fully-qualified abstract or interface name.
         */
        abstract public function getNeeds () : string;
    }
?>