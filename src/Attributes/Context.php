<?php
    /**
     * Project Name:    Wingman Synapse - Attributes - Context
     * Created by:      Angel Politis
     * Creation Date:   Nov 28 2025
     * Last Modified:   Mar 19 2026
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
     * Declares a contextual binding override, specifying which concrete type a consumer
     * should receive when requesting a given abstract.
     * @package Wingman\Synapse\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
    class Context {
        /**
         * Creates a new Context attribute.
         * @param string $consumer The fully-qualified class name of the consuming class.
         * @param string $needs The abstract class or interface the consumer needs overridden.
         * @param ?string $scope The scope name to apply this override in, or null for global.
         */
        public function __construct (
            public string $consumer,
            public string $needs,
            public ?string $scope = null
        ) {}
    }
?>