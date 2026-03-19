<?php
    /**
     * Project Name:    Wingman Synapse - Lazy Proxy
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

    /**
     * A magic-method fallback proxy that defers instantiation of a service until first use.
     *
     * This class intercepts method calls via __call() and resolves the real service from the
     * container on first invocation. It is intentionally lightweight and works without any
     * code generation.
     *
     * LIMITATION: LazyProxy cannot satisfy PHP type hints. If an injection point is declared as
     * CacheInterface $cache, passing a LazyProxy instance will fail PHP's type enforcement at
     * runtime. On PHP 8.4+, Container::proxy() automatically uses ReflectionClass::newLazyProxy()
     * to create a native lazy proxy that IS the correct type. This class is only used as a
     * fallback for PHP < 8.4 or final / abstract classes that cannot be subclassed.
     *
     * @package Wingman\Synapse
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class LazyProxy {
        /**
         * The container used to lazily resolve the proxied service.
         * @var Container
         */
        private Container $container;

        /**
         * The abstract identifier of the service being proxied.
         * @var string
         */
        private string $abstract;

        /**
         * The resolved instance, populated on first method call.
         * @var ?object
         */
        private ?object $instance = null;

        /**
         * Creates a new lazy proxy.
         * @param Container $container The container used to resolve the service on first use.
         * @param string $abstract The abstract identifier of the service to proxy.
         */
        public function __construct (Container $container, string $abstract) {
            $this->container = $container;
            $this->abstract = $abstract;
        }

        /**
         * Intercepts any method call, resolves the underlying service on first invocation,
         * and forwards the call to the real instance.
         * @param string $method The name of the method being called.
         * @param array $args The arguments passed to the method.
         * @return mixed The return value of the delegated method call.
         */
        public function __call (string $method, array $args) : mixed {
            if (!$this->instance) {
                $this->instance = $this->container->get($this->abstract);
            }
            return $this->instance->$method(...$args);
        }
    }
?>