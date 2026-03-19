<?php
    /**
     * Project Name:    Wingman Synapse - Bridge - PSR - Not Found Exception
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

    /**
     * Represents a missing container entry resolution failure.
     *
     * Thrown when the container is asked to resolve an identifier that cannot be
     * found (for example, strict mode with no explicit binding, or an unknown
     * class name that cannot be autoloaded).
     *
     * @package Wingman\Synapse\Bridge\PSR
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class NotFoundException extends ContainerException implements NotFoundExceptionInterface {}
?>