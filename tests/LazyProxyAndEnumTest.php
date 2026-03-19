<?php
    /**
     * Project Name:    Wingman Synapse - Lazy Proxy And Enum Tests
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
    use Wingman\Synapse\Enums\BindingMode;
    use Wingman\Synapse\Enums\NodeState;
    use Wingman\Synapse\Enums\TagScope;
    use Wingman\Synapse\LazyProxy;

    /**
     * Tests lazy proxy behaviour and enum resolve helpers.
     */
    class LazyProxyAndEnumTest extends Test {
        #[Group("Proxy")]
        #[Define(name: "LazyProxy::__call() — Resolves Service Lazily", description: "Proxy should defer service resolution until the first forwarded method call.")]
        public function testLazyProxyResolvesServiceLazily () : void {
            $container = new Container();
            $buildCount = 0;

            $service = new class {
                public function ping () : string {
                    return "pong";
                }
            };

            $container->bindSingleton("pinger", function () use (&$buildCount, $service) {
                $buildCount++;
                return $service;
            });

            $proxy = new LazyProxy($container, "pinger");

            $this->assertTrue($buildCount === 0, "Proxy construction should not resolve the underlying service.");

            $result = $proxy->ping();

            $this->assertTrue($result === "pong", "Proxy should forward method calls to resolved service.");
            $this->assertTrue($buildCount === 1, "Service should be resolved only on first method call.");
        }

        #[Group("Enums")]
        #[Define(name: "BindingMode::resolve() — Handles String And Enum", description: "BindingMode resolver should accept backed strings and existing enum instances.")]
        public function testBindingModeResolveHandlesStringAndEnum () : void {
            $fromString = BindingMode::resolve("singleton");
            $fromEnum = BindingMode::resolve(BindingMode::Transient);

            $this->assertTrue($fromString === BindingMode::Singleton, "BindingMode::resolve() should convert string values to enum instances.");
            $this->assertTrue($fromEnum === BindingMode::Transient, "BindingMode::resolve() should return provided enum instances unchanged.");
        }

        #[Group("Enums")]
        #[Define(name: "NodeState::resolve() — Case-Insensitive String Resolution", description: "NodeState resolver should normalise string input and resolve to enum cases.")]
        public function testNodeStateResolveNormalisesStringInput () : void {
            $resolved = NodeState::resolve("GRAY");

            $this->assertTrue($resolved === NodeState::Gray, "NodeState::resolve() should normalise string values before resolution.");
        }

        #[Group("Enums")]
        #[Define(name: "TagScope::resolve() — Handles String And Enum", description: "TagScope resolver should accept raw values and pass through existing enums.")]
        public function testTagScopeResolveHandlesStringAndEnum () : void {
            $fromString = TagScope::resolve("global");
            $fromEnum = TagScope::resolve(TagScope::Scoped);

            $this->assertTrue($fromString === TagScope::Universal, "TagScope::resolve() should resolve raw backed values.");
            $this->assertTrue($fromEnum === TagScope::Scoped, "TagScope::resolve() should pass through enum instances unchanged.");
        }
    }
?>