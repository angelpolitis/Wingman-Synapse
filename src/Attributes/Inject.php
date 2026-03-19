<?php
    /**
     * Project Name:    Wingman Synapse - Attributes - Inject
     * Created by:      Angel Politis
     * Creation Date:   Nov 29 2025
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
     * Marks a property or parameter for explicit injection, optionally overriding the type hint
     * with a specific service identifier or resolving all services registered under a given tag.
     * @package Wingman\Synapse\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
    class Inject {
        /**
         * Creates a new Inject attribute.
         * @param ?string $service The concrete service identifier to inject, overriding the type hint.
         * @param ?string $tag The tag name whose registered services should be injected as an array.
         */
        public function __construct (
            public ?string $service = null,
            public ?string $tag = null
        ) {}
    }
?>