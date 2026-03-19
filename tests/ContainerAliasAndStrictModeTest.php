<?php
    /**
     * Project Name:    Wingman Synapse - Container Alias And Strict Mode Tests
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
     * Focused tests for aliasing and strict-mode behaviour.
     */
    class ContainerAliasAndStrictModeTest extends Test {
        #[Group("Container")]
        #[Define(name: "alias() — Resolves Through Alias", description: "Aliases should resolve to their mapped abstracts.")]
        public function testAliasResolvesMappedAbstract () : void {
            $container = new Container();
            $service = (object) ["name" => "service"];

            $container->bindSingleton("service.main", fn () => $service);
            $container->alias("main", "service.main");

            $resolved = $container->get("main");

            $this->assertTrue($resolved === $service, "Alias should resolve to the mapped service.");
        }

        #[Group("Container")]
        #[Define(name: "setStrict(true) — Rejects Implicit Autowire", description: "Strict mode should throw when resolving unbound abstracts.")]
        public function testStrictModeRejectsUnboundAbstracts () : void {
            $container = new Container();
            $container->setStrict(true);

            $thrown = false;

            try {
                $container->get("stdClass");
            }
            catch (RuntimeException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "Strict mode should throw for unbound abstracts.");
        }
    }
?>