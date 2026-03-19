<?php
    /**
     * Project Name:    Wingman Synapse - Invoker Resolution Tests
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
    use Exception;
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Synapse\Container;

    /**
     * Focused tests for baseline invoker resolution behaviour.
     */
    class InvokerResolutionTest extends Test {
        #[Group("Invoker")]
        #[Define(name: "call() — Resolves Typed Dependencies", description: "Invoker should resolve typed parameters from the container.")]
        public function testCallResolvesTypedDependencies () : void {
            $container = new Container();
            $invoker = $container->createInvoker();

            $result = $invoker->call(function (Container $resolvedContainer) : bool {
                return $resolvedContainer instanceof Container;
            });

            $this->assertTrue($result === true, "Invoker should resolve typed Container parameters.");
        }

        #[Group("Invoker")]
        #[Define(name: "useParams() — Overrides Primary Parameters", description: "Primary params should override defaults and container resolution.")]
        public function testUseParamsOverridesPrimaryParameters () : void {
            $container = new Container();
            $invoker = $container->createInvoker()->useParams(["name" => "Angel"]);

            $result = $invoker->call(function (string $name = "Default") : string {
                return $name;
            });

            $this->assertTrue($result === "Angel", "useParams() should take precedence over defaults.");
        }

        #[Group("Invoker")]
        #[Define(name: "useExtraParams() — Rejects Positional Arrays", description: "Positional arrays should be rejected for extra params.")]
        public function testUseExtraParamsRejectsPositionalArrays () : void {
            $container = new Container();
            $invoker = $container->createInvoker();

            $thrown = false;

            try {
                $invoker->useExtraParams(["A", "B"]);
            }
            catch (Exception $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "useExtraParams() should throw for positional arrays.");
        }

        #[Group("Invoker")]
        #[Define(name: "useObjects() — Injects Prebuilt Objects", description: "Preloaded objects should satisfy matching typed parameters.")]
        public function testUseObjectsInjectsPrebuiltObjects () : void {
            $container = new Container();
            $invoker = $container->createInvoker();

            $object = new \stdClass();
            $object->name = "prebuilt";

            $result = $invoker
                ->useObjects([$object])
                ->call(function (\stdClass $resolved) use ($object) : bool {
                    return $resolved === $object;
                });

            $this->assertTrue($result === true, "useObjects() should inject matching prebuilt object instances.");
        }
    }
?>