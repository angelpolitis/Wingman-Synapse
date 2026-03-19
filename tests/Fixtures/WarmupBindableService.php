<?php
    /**
     * Project Name:    Wingman Synapse - Tests - Fixture - Warmup Bindable Service
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
    use Wingman\Synapse\Attributes\Bind;

    /**
     * Fixture used to verify warmup attribute scanning behaviour.
     */
    #[Bind(abstract: "synapse.warmup.bound")]
    class WarmupBindableService {}
?>