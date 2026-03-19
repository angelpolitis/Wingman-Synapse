<?php
    /**
     * Project Name:    Wingman Synapse - Attributes - Service
     * Created by:      Angel Politis
     * Creation Date:   Nov 28 2025
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse.Attributes namespace.
    namespace Wingman\Synapse\Attributes;

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Marks a class as an automatically registered service in the container.
     * @package Wingman\Synapse\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_CLASS)]
    class Service {
        /**
         * Creates a new Service attribute.
         * @param bool $singleton Whether the service should be resolved as a singleton.
         * @param ?string $scope The scope name to register this service in, or null for global.
         */
        public function __construct (
            public bool $singleton = true,
            public ?string $scope = null
        ) {}
    }
?>