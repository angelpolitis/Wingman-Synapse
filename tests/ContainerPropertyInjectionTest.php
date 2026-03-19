<?php
    /**
     * Project Name:    Wingman Synapse - Container Property Injection Tests
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
     * Focused tests for automatic property injection behaviour.
     */
    class ContainerPropertyInjectionTest extends Test {
        #[Group("Container")]
        #[Define(name: "Property Injection — Injects Public Typed Properties", description: "Public typed properties should be auto-injected when unset.")]
        public function testPropertyInjectionInjectsPublicTypedProperties () : void {
            $container = new Container();

            $class = get_class(new class {
                public Container $container;
                public function hasContainer () : bool { return isset($this->container); }
            });

            $resolved = $container->get($class);

            $this->assertTrue($resolved->hasContainer(), "Container should inject uninitialised public typed properties.");
        }

        #[Group("Container")]
        #[Define(name: "setInjectableOnlyWithAttributes() — Restricts Injection", description: "When enabled, only properties marked with #[Inject] should be auto-injected.")]
        public function testInjectableOnlyWithAttributesRestrictsInjection () : void {
            $container = new Container();
            $container->setInjectableOnlyWithAttributes(true);

            $withoutAttributeClass = get_class(new class {
                public Container $container;
                public function hasContainer () : bool { return isset($this->container); }
            });

            $withAttributeClass = get_class(new class {
                #[\Wingman\Synapse\Attributes\Inject(service: Container::class)]
                public Container $container;
                public function hasContainer () : bool { return isset($this->container); }
            });

            $withoutAttribute = $container->get($withoutAttributeClass);
            $withAttribute = $container->get($withAttributeClass);

            $this->assertTrue(!$withoutAttribute->hasContainer(), "Property without #[Inject] should not be injected when restricted mode is enabled.");
            $this->assertTrue($withAttribute->hasContainer(), "Property with #[Inject] should be injected when restricted mode is enabled.");
        }
    }
?>