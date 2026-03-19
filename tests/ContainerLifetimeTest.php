<?php
    /**
     * Project Name:    Wingman Synapse - Container Lifetime Tests
     * Created by:      Angel Politis
     * Creation Date:   Mar 18 2026
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse.Tests namespace.
    namespace Wingman\Synapse\Tests;

    # Import the following classes to the current scope.
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Synapse\Container;

    /**
     * Focused tests for binding lifetimes.
     */
    class ContainerLifetimeTest extends Test {
        #[Group("Container")]
        #[Define(name: "bindSingleton() — Reuses Same Instance", description: "Singleton bindings should resolve once and then be reused.")]
        public function testBindSingletonReusesSameInstance () : void {
            $container = new Container();
            $buildCount = 0;

            $container->bindSingleton("clock", function () use (&$buildCount) {
                $buildCount++;
                return (object) ["count" => $buildCount];
            });

            $first = $container->get("clock");
            $second = $container->get("clock");

            $this->assertTrue($first === $second, "Singleton should return the exact same object instance.");
            $this->assertTrue($buildCount === 1, "Singleton should be built only once.");
        }

        #[Group("Container")]
        #[Define(name: "bindTransient() — Returns Fresh Instances", description: "Transient bindings should build a fresh object on every get().")]
        public function testBindTransientReturnsFreshInstances () : void {
            $container = new Container();
            $buildCount = 0;

            $container->bindTransient("token", function () use (&$buildCount) {
                $buildCount++;
                return (object) ["count" => $buildCount];
            });

            $first = $container->get("token");
            $second = $container->get("token");

            $this->assertTrue($first !== $second, "Transient bindings should not reuse instances.");
            $this->assertTrue($buildCount === 2, "Transient bindings should build on every resolution.");
        }
    }
?>