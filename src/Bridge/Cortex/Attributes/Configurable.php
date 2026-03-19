<?php
    /**
     * Project Name:    Wingman Synapse - Cortex Configurable Attribute Bridge
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse.Bridge.Cortex.Attributes namespace.
    namespace Wingman\Synapse\Bridge\Cortex\Attributes;

    # Guard against double-inclusion (e.g. via symlinked paths resolving to different strings
    # under require_once). If the alias or stub is already in place there is nothing to do.
    if (class_exists(__NAMESPACE__ . '\\Configurable', false)) return;

    # Import the following classes to the current scope.
    use Attribute;

    # If Cortex is available, alias the real attribute; otherwise provide a lightweight
    # local implementation with the same constructor/getter shape used by the bridge hydrate().
    if (class_exists(\Wingman\Cortex\Attributes\Configurable::class)) {
        class_alias(\Wingman\Cortex\Attributes\Configurable::class, __NAMESPACE__ . '\\Configurable');
    }
    else {
        #[Attribute]
        class Configurable {
            /**
             * The configuration key.
             * @var string
             */
            private string $key;

            /**
             * The optional configuration description.
             * @var ?string
             */
            private ?string $description;

            /**
             * Creates a new Configurable attribute.
             * @param string $key The dot-notation configuration key.
             * @param string|null $description Optional key description.
             */
            public function __construct (string $key, ?string $description = null) {
                $this->key = $key;
                $this->description = $description;
            }

            /**
             * Returns the configuration key.
             * @return string
             */
            public function getKey () : string {
                return $this->key;
            }

            /**
             * Returns the optional configuration description.
             * @return string|null
             */
            public function getDescription () : ?string {
                return $this->description;
            }
        }
    }
?>