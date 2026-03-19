<?php
    /**
     * Project Name:    Wingman Synapse - Enums - Tag Scope
     * Created by:      Angel Politis
     * Creation Date:   Mar 18 2026
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse.Enums namespace.
    namespace Wingman\Synapse\Enums;

    /**
     * Enumerates the two tag registries consulted during tag-based service lookup.
     *
     * Tags are stored in two parallel stores: a universal store that applies
     * regardless of the active scope, and a scope-specific store that is keyed by
     * scope name. This enum is used by collectTags() to select which store to query,
     * eliminating magic string discriminators in the container internals.
     *
     * @package Wingman\Synapse\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum TagScope : string {
        /**
         * The application-wide tag registry, shared across all scopes.
         * Corresponds to the $tags property on the container.
         */
        case Universal = "global";

        /**
         * The scope-specific tag registry, keyed by the active scope name.
         * Corresponds to the $scopeTags property on the container.
         */
        case Scoped = "scoped";

        /**
         * Resolves a TagScope instance from a backed string value or returns
         * the given instance unchanged.
         * @param self|string $value A TagScope instance or its raw backed value.
         * @return static The resolved tag scope.
         */
        public static function resolve (self|string $value) : static {
            if ($value instanceof self) {
                return $value;
            }
            return self::from($value);
        }
    }
?>