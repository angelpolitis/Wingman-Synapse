<?php
    /**
     * Project Name:    Wingman Synapse - Provider Manager Deferred And Failure Tests
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
    use Wingman\Synapse\Provider;
    use Wingman\Synapse\ProviderManager;

    /**
     * Focused tests for deferred provider behaviour and failure signalling.
     */
    class ProviderManagerDeferredAndFailureTest extends Test {
        #[Group("Provider")]
        #[Define(name: "Deferred Provider — Loads On First Abstract Resolution", description: "Deferred providers should register and boot only when provided abstracts are requested.")]
        public function testDeferredProviderLoadsOnFirstAbstractResolution () : void {
            $container = new Container();
            $manager = new ProviderManager($container);
            $log = [];

            $deferred = new class ($container, $log) extends Provider {
                private array $log;
                public function __construct (Container $container, array &$log) { parent::__construct($container); $this->log =& $log; }
                public function isDeferred () : bool { return true; }
                public function getProvidedAbstracts () : array { return ["deferred.service"]; }
                public function register () : void { $this->log[] = "deferred.register"; $this->container->bindSingleton("deferred.service", fn () => (object) ["ok" => true]); }
                public function boot () : void { $this->log[] = "deferred.boot"; }
            };

            $manager->addProvider($deferred)->boot();
            $this->assertTrue($log === [], "Deferred provider should remain inactive during manager boot().");

            $resolved = $container->get("deferred.service");

            $this->assertTrue(isset($resolved->ok) && $resolved->ok === true, "Deferred service should resolve after on-demand load.");
            $this->assertTrue($log === ["deferred.register", "deferred.boot"], "Deferred provider should register and boot on first resolution.");
        }

        #[Group("Provider")]
        #[Define(name: "Duplicate Deferred Abstracts — Throws", description: "Conflicting deferred abstract ownership should fail fast.")]
        public function testDuplicateDeferredAbstractsThrow () : void {
            $container = new Container();
            $manager = new ProviderManager($container);

            $providerOne = new class ($container) extends Provider {
                public function isDeferred () : bool { return true; }
                public function getProvidedAbstracts () : array { return ["shared.abstract"]; }
            };

            $providerTwo = new class ($container) extends Provider {
                public function isDeferred () : bool { return true; }
                public function getProvidedAbstracts () : array { return ["shared.abstract"]; }
            };

            $thrown = false;

            try {
                $manager->addProviders([$providerOne, $providerTwo])->boot();
            }
            catch (RuntimeException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "Manager should throw when duplicate deferred abstract mappings are declared.");
        }

        #[Group("Provider")]
        #[Define(name: "onFailed() — Receives Registration Failures", description: "Failure listeners should be invoked when provider registration throws.")]
        public function testOnFailedReceivesRegistrationFailures () : void {
            $container = new Container();
            $manager = new ProviderManager($container);
            $failedEventFired = false;

            $failingProvider = new class ($container) extends Provider {
                public function register () : void { throw new RuntimeException("register failed"); }
            };

            $manager->onFailed(function (string $providerClass, mixed $provider, \Throwable $error) use (&$failedEventFired) {
                $failedEventFired = $providerClass !== "" && $error instanceof RuntimeException;
            });

            $thrown = false;

            try {
                $manager->addProvider($failingProvider)->boot();
            }
            catch (RuntimeException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "Manager should throw when provider registration fails.");
            $this->assertTrue($failedEventFired, "onFailed listener should be invoked for provider failures.");
        }
    }
?>