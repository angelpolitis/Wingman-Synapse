<?php
    /**
     * Project Name:    Wingman Synapse - Enums - Node State
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
     * Enumerates the visit states of a node in a depth-first search traversal.
     *
     * Based on the standard tricolour DFS model: nodes begin unrepresented in the
     * state map (unvisited), transition to Gray when first entered (on the active
     * path), and finally to Black when fully processed and safe to skip.
     *
     * @package Wingman\Synapse\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum NodeState : string {
        /**
         * The node is currently on the active DFS path.
         * A back-edge to a Gray node indicates a cycle.
         */
        case Gray = "gray";

        /**
         * The node and all its descendants have been fully processed.
         * Re-encountering a Black node is safe to skip.
         */
        case Black = "black";

        /**
         * Resolves a node state from a string or returns the existing instance.
         *
         * @param static|string $state The state to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|string $state) : static {
            return $state instanceof static ? $state : static::from(strtolower($state));
        }
    }
?>