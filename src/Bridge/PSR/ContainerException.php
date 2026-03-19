<?php
    /**
     * Project Name:    Wingman Synapse - Bridge - PSR - Container Exception
     * Created by:      Angel Politis
     * Creation Date:   Mar 18 2026
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse.Bridge.PSR namespace.
    namespace Wingman\Synapse\Bridge\PSR;

    # Import the following classes to the current scope.
    use RuntimeException;

    /**
     * Represents a generic container resolution failure.
     *
     * This exception is used for failures that occur while creating or wiring a
     * known container entry (for example, unresolvable constructor parameters,
     * circular dependencies, or non-instantiable concretes).
     *
     * @package Wingman\Synapse\Bridge\PSR
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ContainerException extends RuntimeException implements ContainerExceptionInterface {}
?>