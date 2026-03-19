<?php
    /**
     * Project Name:    Wingman Synapse - Bridge - PSR - Container Exception Interface
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

    if (interface_exists('Psr\Container\ContainerExceptionInterface')) {
        /**
         * PSR-11 compatibility bridge for container-level resolution exceptions.
         *
         * When psr/container is installed this interface extends
         * Psr\Container\ContainerExceptionInterface, so implementations can satisfy
         * both internal and PSR-11 exception contracts. When psr/container is absent
         * the interface is an empty marker, preserving compatibility in lightweight
         * environments.
         *
         * @package Wingman\Synapse\Bridge\PSR
         * @author Angel Politis <info@angelpolitis.com>
         * @since 1.0
         * @disregard The PSR-11 contract is not actually implemented by the stub; this interface only extends it when psr/container is installed.
         * @psalm-suppress UndefinedInterface
         * @noinspection PhpUndefinedClassInspection
         */
        interface ContainerExceptionInterface extends \Psr\Container\ContainerExceptionInterface {}
    }
    else {
        /**
         * PSR-11 compatibility bridge for container-level resolution exceptions.
         *
         * When psr/container is installed this interface extends
         * Psr\Container\ContainerExceptionInterface, so implementations can satisfy
         * both internal and PSR-11 exception contracts. When psr/container is absent
         * the interface is an empty marker, preserving compatibility in lightweight
         * environments.
         *
         * @package Wingman\Synapse\Bridge\PSR
         * @author Angel Politis <info@angelpolitis.com>
         * @since 1.0
         */
        interface ContainerExceptionInterface {}
    }
?>