<?php
    /**
     * Project Name:    Wingman Synapse - Interfaces - Bind
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
     * Defines the contract for classes that carry a bindable abstract type identifier.
     * @package Wingman\Synapse\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface Bind {
        /**
         * Returns the abstract class or interface name this binding is associated with.
         * @return string
         */
        public function getAbstract () : string;
    }
?>