<?php
    /**
     * Project Name:    Wingman Synapse - Container Factory And State Tests
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
     * Focused tests for factories and mutable container state operations.
     */
    class ContainerFactoryAndStateTest extends Test {
        #[Group("Container")]
        #[Define(name: "registerFactory() — Resolves Factory-Backed Services", description: "Factory-registered identifiers should resolve through the configured factory.")]
        public function testRegisterFactoryResolvesFactoryBackedService () : void {
            $container = new Container();

            $container->registerFactory("factory.key", fn (Container $c) => (object) ["id" => "factory.key"]);

            $resolved = $container->get("factory.key");

            $this->assertTrue(isset($resolved->id) && $resolved->id === "factory.key", "Factory should produce the expected service payload.");
        }

        #[Group("Container")]
        #[Define(name: "forget() — Removes Binding State", description: "forget() should remove explicit binding state and cached instances.")]
        public function testForgetRemovesBindingState () : void {
            $container = new Container();
            $container->bindSingleton("cache.item", fn () => (object) []);
            $container->get("cache.item");

            $container->forget("cache.item");

            $this->assertTrue(!$container->hasBinding("cache.item"), "forget() should remove explicit binding state.");
            $this->assertTrue(!$container->has("cache.item"), "forget() should make unknown abstract unresolved.");
        }

        #[Group("Container")]
        #[Define(name: "fork() — Copies Config Without Sharing Instances", description: "fork() should clone registration config but isolate resolved instance stores.")]
        public function testForkCopiesConfigurationWithoutSharingInstances () : void {
            $container = new Container();
            $container->bindSingleton("clock", fn () => (object) ["id" => uniqid("clock", true)]);

            $parentInstance = $container->get("clock");
            $child = $container->fork();
            $childInstance = $child->get("clock");

            $this->assertTrue($container->hasBinding("clock"), "Parent should retain the binding.");
            $this->assertTrue($child->hasBinding("clock"), "Child should inherit binding configuration.");
            $this->assertTrue($parentInstance !== $childInstance, "Forked container should not share resolved singleton instances with parent.");
        }
    }
?>