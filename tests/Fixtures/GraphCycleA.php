<?php
    /**
     * Project Name:    Wingman Synapse - Tests - Fixture - Graph Cycle A
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

    /**
     * Cycle fixture node A.
     */
    class GraphCycleA {
        public function __construct (public GraphCycleB $b) {}
    }
?>