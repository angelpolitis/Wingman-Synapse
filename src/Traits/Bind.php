<?php
    /**
     * Project Name:    Wingman Synapse - Traits - Bind
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
     * Provides the implementation glue for classes that self-declare a binding via code
     * rather than via the #[Bind] attribute.
     *
     * The using class must implement getAbstract() to return the abstract or interface
     * this class should be bound to, satisfying the Interfaces\Bind contract.
     *
     * @package Wingman\Synapse\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait Bind {
        /**
         * Return the abstract or interface this class should be bound to in the container.
         * @return string Fully-qualified abstract or interface name.
         */
        abstract public function getAbstract () : string;
    }
?>