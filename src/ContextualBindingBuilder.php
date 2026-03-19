<?php
    /**
     * Project Name:    Wingman Synapse - Contextual Binding Builder
     * Created by:      Angel Politis
     * Creation Date:   Nov 28 2025
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse namespace.
    namespace Wingman\Synapse;

    # Import the following classes to the current scope.
    use RuntimeException;

    /**
     * Represents a contextual binding builder used to construct conditional
     * service overrides via the fluent when()->needs()->give() API.
     * @package Wingman\Synapse
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ContextualBindingBuilder {
        /**
         * The container that owns this builder.
         * @var Container
         */
        protected Container $container;

        /**
         * The consumer class this contextual binding applies to.
         * @var string
         */
        protected string $consumer;

        /**
         * The abstract type the consumer needs overridden, set via needs().
         * @var ?string
         */
        protected ?string $needs = null;

        /**
         * Creates a new contextual binding builder.
         * @param Container $container The owning container.
         * @param string $consumer The consumer class for which to create a contextual binding.
         */
        public function __construct (Container $container, string $consumer) {
            $this->container = $container;
            $this->consumer = $consumer;
        }

        /**
         * Registers the concrete implementation that the container will inject when
         * the consumer requests the abstract type specified via needs().
         * @param string|callable|object $concrete The concrete class name, factory callable, or instance.
         * @throws RuntimeException If needs() has not been called before give().
         * @return void
         */
        public function give (string|callable|object $concrete) : void {
            if ($this->needs === null) {
                throw new RuntimeException("You must call needs() before give().");
            }

            $this->container->setContextualBinding($this->consumer, $this->needs, $concrete);
        }

        /**
         * Specifies which abstract type should be overridden for the consumer.
         * @param string $abstract The abstract class or interface name to override.
         * @return self
         */
        public function needs (string $abstract) : self {
            $this->needs = $abstract;
            return $this;
        }
    }
?>