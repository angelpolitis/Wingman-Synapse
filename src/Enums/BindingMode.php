<?php
    /**
     * Project Name:    Wingman Synapse - Enums - Binding Mode
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
     * Enumerates the three instance-lifetime strategies available for container bindings.
     *
     * Understanding the distinction between Scoped and Transient is important:
     *
     * - **Singleton** — one instance for the entire container lifetime, stored in
     *   `$instances`. The same object is returned on every call to `get()`, regardless
     *   of the active scope.
     *
     * - **Scoped** — one instance per scope lifetime, stored in `$scopeInstances`. The
     *   first `get()` within a scope builds and caches the object; subsequent calls
     *   within the same scope return the cached copy. When the scope exits the instance
     *   is discarded. This is the default lifetime for bindings that do not specify a
     *   mode.
     *
     * - **Transient** — a fresh instance on every `get()` call. The object is never
     *   cached. This is equivalent to always calling `make()`.
     *
     * @package Wingman\Synapse\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum BindingMode : string {
        /**
         * One shared instance for the entire container lifetime.
         */
        case Singleton = "singleton";

        /**
         * One instance per active scope; discarded when the scope exits.
         */
        case Scoped = "scoped";

        /**
         * A fresh instance on every resolution; never cached.
         */
        case Transient = "transient";

        /**
         * Resolves a binding mode from a string or returns the existing instance.
         * @param static|string $mode The mode to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|string $mode) : static {
            return $mode instanceof static ? $mode : static::from(strtolower($mode));
        }
    }
?>