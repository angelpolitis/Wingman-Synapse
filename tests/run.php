<?php
    /**
     * Project Name:    Wingman Synapse - Test Runner
     * Created by:      Angel Politis
     * Creation Date:   Mar 18 2026
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Import the following classes to the current scope.
    use Wingman\Argus\Tester;

    require_once __DIR__ . "/../../../_temp_bootstrap.php";

    foreach (glob(__DIR__ . "/Fixtures/*.php") ?: [] as $fixture) {
        require_once $fixture;
    }

    if (!class_exists(Tester::class)) {
        http_response_code(500);
        echo "Argus test framework not found. Install wingman/argus alongside wingman/synapse.";
        exit(1);
    }

    Tester::runTestsInDirectory(__DIR__, "Wingman\\Synapse\\Tests");
?>