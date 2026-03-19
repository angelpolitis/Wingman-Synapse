<?php
    /**
     * Project Name:    Wingman Synapse - Provider Base Tests
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
    use ReflectionMethod;
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Synapse\Container;
    use Wingman\Synapse\Provider;

    /**
     * Tests base provider defaults and helper behaviours.
     */
    class ProviderBaseTest extends Test {
        #[Group("Provider")]
        #[Define(name: "Provider Defaults — Match Expected Baseline", description: "Base provider should expose default lifecycle metadata and container access.")]
        public function testProviderDefaultsMatchExpectedBaseline () : void {
            $container = new Container();

            $provider = new class ($container) extends Provider {};

            $this->assertTrue($provider->getContainer() === $container, "Provider should retain the container passed to constructor.");
            $this->assertTrue($provider->getPriority() === 0, "Default provider priority should be zero.");
            $this->assertTrue(!$provider->isDeferred(), "Provider should be non-deferred by default.");
            $this->assertTrue($provider->getDependencies() === [], "Provider dependencies should be empty by default.");
            $this->assertTrue($provider->getProvidedAbstracts() === [], "Provider provided abstracts should be empty by default.");
        }

        #[Group("Provider")]
        #[Define(name: "mergeConfig() — Deep Merges Arrays", description: "Provider helper should recursively merge defaults with overrides.")]
        public function testMergeConfigDeepMergesArrays () : void {
            $container = new Container();

            $provider = new class ($container) extends Provider {};
            $mergeConfig = new ReflectionMethod(Provider::class, "mergeConfig");
            $mergeConfig->setAccessible(true);

            $merged = $mergeConfig->invoke(
                $provider,
                ["cache" => ["enabled" => true, "ttl" => 60], "name" => "default"],
                ["cache" => ["ttl" => 300], "name" => "custom"]
            );

            $this->assertTrue($merged["cache"]["enabled"] === true, "Deep merge should preserve unspecified nested defaults.");
            $this->assertTrue($merged["cache"]["ttl"] === 300, "Deep merge should overwrite nested values when provided.");
            $this->assertTrue($merged["name"] === "custom", "Top-level override should replace default scalar values.");
        }

        #[Group("Provider")]
        #[Define(name: "onResolved() — Hooks Resolution Callback", description: "Provider helper should register post-resolution callbacks through the container.")]
        public function testOnResolvedHooksResolutionCallback () : void {
            $container = new Container();
            $triggered = false;

            $provider = new class ($container, $triggered) extends Provider {
                private bool $triggered;

                public function __construct (Container $container, bool &$triggered) {
                    parent::__construct($container);
                    $this->triggered =& $triggered;
                }

                public function register () : void {
                    $this->onResolved("sample.service", function (mixed $instance, Container $container) {
                        $this->triggered = is_object($instance) && $container instanceof Container;
                    });
                }
            };

            $provider->register();
            $container->bind("sample.service", fn () => (object) ["ok" => true]);
            $container->get("sample.service");

            $this->assertTrue($triggered, "onResolved() should run when the observed abstract is resolved.");
        }
    }
?>