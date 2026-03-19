<?php
    /**
     * Project Name:    Wingman Synapse - Container Contextual Resolution Tests
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
     * Focused tests for contextual dependency overrides.
     */
    class ContainerContextualResolutionTest extends Test {
        #[Group("Container")]
        #[Define(name: "setContextualBinding() — Overrides Consumer Dependency", description: "Consumer-specific overrides should be honoured during constructor resolution.")]
        public function testSetContextualBindingOverridesConsumerDependency () : void {
            $container = new Container();

            $consumerClass = get_class(new class (new Container()) {
                public function __construct (public Container $container) {}
            });

            $contextualContainer = $container->fork();
            $container->setContextualBinding($consumerClass, Container::class, $contextualContainer);

            $resolved = $container->make($consumerClass);

            $this->assertTrue($resolved->container instanceof Container, "Resolved consumer should receive a Container dependency.");
            $this->assertTrue($resolved->container === $contextualContainer, "Contextual override should inject the configured container instance.");
        }
    }
?>