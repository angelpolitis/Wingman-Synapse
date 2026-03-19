<?php
    /**
     * Project Name:    Wingman Synapse - Signal
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
     * Represents a signal emitted by the Synapse container during its lifecycle operations.
     *
     * Each case maps to a dot-notation string identifier consumed by Corvus listeners.
     * Cases can be passed directly to Emitter::emit() — coercion to their string value is automatic.
     *
     * @package Wingman\Synapse\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum Signal : string {
        /**
         * Emitted when a binding is registered in the container.
         * Payload: `id` (string — abstract identifier).
         */
        case BOUND = "synapse.bound";

        /**
         * Emitted when a scope is entered.
         * Payload: `scope` (string — scope name).
         */
        case SCOPE_ENTERED = "synapse.scope.entered";

        /**
         * Emitted when a scope is exited and its instance caches are cleared.
         * Payload: `scope` (string — scope name).
         */
        case SCOPE_EXITED = "synapse.scope.exited";

        /**
         * Emitted after a service has been fully resolved and all extenders applied.
         * Payload: `id` (string — abstract identifier).
         */
        case RESOLVED = "synapse.resolved";

        /**
         * Emitted immediately after a service is constructed, before extenders are applied.
         * Payload: `id` (string — abstract identifier).
         */
        case RESOLVING = "synapse.resolving";

        /**
         * Resolves a signal from a string or returns the existing instance.
         * @param static|string $signal The signal to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|string $signal) : static {
            return $signal instanceof static ? $signal : static::from(strtolower($signal));
        }
    }
?>