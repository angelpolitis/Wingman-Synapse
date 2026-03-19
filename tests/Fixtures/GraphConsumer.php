<?php
    /**
     * Project Name:    Wingman Synapse - Tests - Fixtures - Graph Consumer
     * Created by:      Angel Politis
     * Creation Date:   Mar 18 2026
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse.Tests.Fixtures namespace.
    namespace Wingman\Synapse\Tests\Fixtures;

    # Import the following classes to the current scope.
    use ArrayObject;

    /**
     * Deterministic fixture used for dependency graph output assertions.
     */
    class GraphConsumer {
        public function __construct (public ArrayObject $dependency) {}
    }
?>