<?php
    /**
     * Project Name:    Wingman Synapse - Container Scopes And Tags Tests
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
     * Focused tests for scope lifecycle and tag-based resolution.
     */
    class ContainerScopesAndTagsTest extends Test {
        #[Group("Container")]
        #[Define(name: "getByTag() — Respects Priority And Filter", description: "Tag lookup should sort by priority and then apply optional filters.")]
        public function testGetByTagRespectsPriorityAndFilter () : void {
            $container = new Container();

            $container->bind("service.low", fn () => (object) ["name" => "low"]);
            $container->bind("service.high", fn () => (object) ["name" => "high"]);
            $container->tag("workers", "service.low", 1);
            $container->tag("workers", "service.high", 10);

            $all = $container->getByTag("workers");
            $filtered = $container->getByTag("workers", fn (object $s) => $s->name === "high");

            $this->assertTrue(count($all) === 2, "Tag lookup should return both tagged services.");
            $this->assertTrue($all[0]->name === "high", "Higher-priority tagged service should be returned first.");
            $this->assertTrue(count($filtered) === 1 && $filtered[0]->name === "high", "Tag filter should trim the resolved service list.");
        }

        #[Group("Container")]
        #[Define(name: "Scopes — Enter And Exit Controls Scoped Lifetime", description: "Scoped services should be reused in-scope and recreated after scope exit.")]
        public function testScopesControlScopedLifetime () : void {
            $container = new Container();
            $container->registerScope("request");

            $buildCount = 0;
            $container->bind("request.service", function () use (&$buildCount) {
                $buildCount++;
                return (object) ["count" => $buildCount];
            }, ["scope" => "request"]);

            $container->enterScope("request");
            $first = $container->get("request.service");
            $second = $container->get("request.service");
            $container->exitScope();

            $container->enterScope("request");
            $third = $container->get("request.service");
            $container->exitScope();

            $this->assertTrue($first === $second, "Scoped service should be reused inside the same scope.");
            $this->assertTrue($first !== $third, "Scoped service should be rebuilt after scope exit.");
        }

        #[Group("Container")]
        #[Define(name: "exitScope() — Throws On Global Scope", description: "Global scope cannot be exited.")]
        public function testExitScopeThrowsOnGlobalScope () : void {
            $container = new Container();

            $thrown = false;
            try {
                $container->exitScope();
            }
            catch (RuntimeException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "Exiting global scope should throw.");
        }
    }
?>