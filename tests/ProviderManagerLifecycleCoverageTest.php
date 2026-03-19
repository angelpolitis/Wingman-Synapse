<?php
    /**
     * Project Name:    Wingman Synapse - Provider Manager Lifecycle Coverage Tests
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
     * Thorough tests for provider manager lifecycle listeners and failure branches.
     */
    class ProviderManagerLifecycleCoverageTest extends Test {
        #[Group("Provider")]
        #[Define(name: "Lifecycle Listeners - Fire In Strict Order", description: "registering/registered/booting/booted callbacks should fire in deterministic order.")]
        public function testLifecycleListenersFireInStrictOrder () : void {
            $container = new Container();
            $manager = new ProviderManager($container);
            $events = [];

            $provider = new class ($container, $events) extends Provider {
                private array $events;

                public function __construct (Container $container, array &$events) {
                    parent::__construct($container);
                    $this->events =& $events;
                }

                public function register () : void {
                    $this->events[] = "register.method";
                }

                public function boot () : void {
                    $this->events[] = "boot.method";
                }
            };

            $manager
                ->onRegistering(function () use (&$events) {
                    $events[] = "registering.listener";
                })
                ->onRegistered(function () use (&$events) {
                    $events[] = "registered.listener";
                })
                ->onBooting(function () use (&$events) {
                    $events[] = "booting.listener";
                })
                ->onBooted(function () use (&$events) {
                    $events[] = "booted.listener";
                })
                ->addProvider($provider)
                ->boot();

            $this->assertTrue($events === [
                "registering.listener",
                "register.method",
                "registered.listener",
                "booting.listener",
                "boot.method",
                "booted.listener",
            ], "Provider lifecycle listeners should fire in deterministic strict order.");
        }

        #[Group("Provider")]
        #[Define(name: "resolveDeferred() - Noop For Unknown Abstract", description: "resolveDeferred() should be safe and side-effect free for unknown abstract identifiers.")]
        public function testResolveDeferredNoopForUnknownAbstract () : void {
            $container = new Container();
            $manager = new ProviderManager($container);

            $result = $manager->resolveDeferred("missing.abstract");

            $this->assertTrue($result === $manager, "resolveDeferred() should return the manager when abstract is unknown.");
        }

        #[Group("Provider")]
        #[Define(name: "boot() - Throws For Missing Provider Dependency", description: "ProviderManager should throw when declared provider dependencies are not registered.")]
        public function testBootThrowsForMissingProviderDependency () : void {
            $container = new Container();
            $manager = new ProviderManager($container);

            $provider = new class ($container) extends Provider {
                public function getDependencies () : array {
                    return ["MissingProviderClass"];
                }
            };

            $thrown = false;

            try {
                $manager->addProvider($provider)->boot();
            }
            catch (RuntimeException $error) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "boot() should throw when provider dependencies are missing.");
        }

        #[Group("Provider")]
        #[Define(name: "boot() - Throws For Circular Dependencies", description: "ProviderManager should throw when provider dependency graph contains cycles.")]
        public function testBootThrowsForCircularDependencies () : void {
            $container = new Container();
            $manager = new ProviderManager($container);

            $providerA = new class ($container) extends Provider {
                public function getDependencies () : array {
                    return ["CycleProviderB"];
                }
            };

            $providerB = new class ($container) extends Provider {
                public function getDependencies () : array {
                    return ["CycleProviderA"];
                }
            };

            class_alias($providerA::class, "CycleProviderA");
            class_alias($providerB::class, "CycleProviderB");

            $thrown = false;

            try {
                $manager->addProviders(["CycleProviderA", "CycleProviderB"])->boot();
            }
            catch (RuntimeException $error) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "boot() should throw when provider graph contains cycles.");
        }

        #[Group("Provider")]
        #[Define(name: "onFailed() - Receives Boot Failures", description: "Failure listener should receive provider boot exceptions as well as registration exceptions.")]
        public function testOnFailedReceivesBootFailures () : void {
            $container = new Container();
            $manager = new ProviderManager($container);
            $failed = false;

            $provider = new class ($container) extends Provider {
                public function register () : void {}

                public function boot () : void {
                    throw new RuntimeException("boot failure");
                }
            };

            $manager->onFailed(function (string $providerClass, mixed $provider, \Throwable $error) use (&$failed) {
                $failed = $providerClass !== "" && $error instanceof RuntimeException;
            });

            $thrown = false;

            try {
                $manager->addProvider($provider)->boot();
            }
            catch (RuntimeException $error) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "Manager should throw when provider boot fails.");
            $this->assertTrue($failed, "onFailed listener should fire for boot failures.");
        }
    }
?>