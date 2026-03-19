<?php
    /**
     * Project Name:    Wingman Synapse - Contextual Binding Builder Tests
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
    use RuntimeException;
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Synapse\Container;

    /**
     * Tests fluent contextual binding builder behaviour.
     */
    class ContextualBindingBuilderTest extends Test {
        #[Group("Container")]
        #[Define(name: "give() — Throws Without needs()", description: "Calling give() before needs() should fail fast.")]
        public function testGiveThrowsWithoutNeeds () : void {
            $container = new Container();
            $builder = $container->forConsumer("ConsumerClass");

            $thrown = false;

            try {
                $builder->give(Container::class);
            }
            catch (RuntimeException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "ContextualBindingBuilder::give() should throw if needs() was not called first.");
        }

        #[Group("Container")]
        #[Define(name: "needs()->give() — Registers Contextual Override", description: "Fluent builder should configure and apply contextual bindings in container resolution.")]
        public function testNeedsGiveRegistersContextualOverride () : void {
            $container = new Container();

            $consumerClass = get_class(new class (new Container()) {
                public function __construct (public Container $container) {}
            });

            $contextualContainer = $container->fork();

            $container
                ->forConsumer($consumerClass)
                ->needs(Container::class)
                ->give($contextualContainer);

            $resolved = $container->make($consumerClass);

            $this->assertTrue($resolved->container instanceof Container, "Consumer should receive a container dependency.");
            $this->assertTrue($resolved->container === $contextualContainer, "Configured contextual override should be used during resolution.");
        }
    }
?>