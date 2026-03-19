<?php
    /**
     * Project Name:    Wingman Synapse - Attributes - Bind
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
     * Marks a class as a binding target, associating it with an abstract type in the container.
     * @package Wingman\Synapse\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_CLASS)]
    class Bind {
        /**
         * Creates a new Bind attribute.
         * @param string $abstract The abstract class or interface to bind to.
         * @param bool $singleton Whether the binding should be resolved as a singleton.
         * @param ?string $scope The scope name to restrict this binding to, or null for global.
         * @param bool $lazy Whether the binding should be resolved lazily.
         */
        public function __construct (
            public string $abstract,
            public bool $singleton = false,
            public ?string $scope = null,
            public bool $lazy = false
        ) {}
    }
?>