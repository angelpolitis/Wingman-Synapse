<?php
    /**
     * Project Name:    Wingman Synapse - Invoker Factory And Inject Service Tests
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
    use Wingman\Synapse\Interfaces\Factory;

    /**
     * Focused tests for invoker factory modes and Inject(service) override behaviour.
     */
    class InvokerFactoryAndInjectServiceTest extends Test {
        #[Group("Invoker")]
        #[Define(name: "useFactory(callable) — Creates Typed Dependencies", description: "Callable factory should override typed dependency resolution.")]
        public function testUseFactoryCallableCreatesTypedDependencies () : void {
            $container = new Container();
            $invoker = $container->createInvoker();

            $result = $invoker
                ->useFactory(fn (Container $c, string $id) => $c->fork())
                ->call(function (Container $resolvedContainer) use ($container) : bool {
                    return $resolvedContainer !== $container;
                });

            $this->assertTrue($result === true, "Callable factory should override default typed dependency resolution.");
        }

        #[Group("Invoker")]
        #[Define(name: "useFactory(Factory) — Uses Factory Interface", description: "Factory interface implementations should be honoured by Invoker.")]
        public function testUseFactoryInterfaceCreatesTypedDependencies () : void {
            $container = new Container();
            $invoker = $container->createInvoker();

            $factory = new class implements Factory {
                public function create (Container $container, string $id) : object {
                    return $container->fork();
                }
            };

            $result = $invoker
                ->useFactory($factory)
                ->call(function (Container $resolvedContainer) use ($container) : bool {
                    return $resolvedContainer !== $container;
                });

            $this->assertTrue($result === true, "Factory interface should create the dependency instance.");
        }

        #[Group("Invoker")]
        #[Define(name: "Invalid Factory String — Throws RuntimeException", description: "String factory resolving to non-callable instances should throw.")]
        public function testInvalidFactoryStringThrowsRuntimeException () : void {
            $container = new Container();

            $factoryClass = get_class(new class {
                public function ping () : string { return "pong"; }
            });

            $container->bindSingleton($factoryClass, fn () => new $factoryClass());

            $invoker = $container->createInvoker()->useFactory($factoryClass);
            $thrown = false;

            try {
                $invoker->call(function (Container $resolvedContainer) : bool {
                    return $resolvedContainer instanceof Container;
                });
            }
            catch (RuntimeException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "Invoker should throw when string factory resolves to a non-callable instance.");
        }

        #[Group("Invoker")]
        #[Define(name: "#[Inject(service)] — Overrides Typed Parameter Service", description: "Inject(service) should override typed parameter resolution.")]
        public function testInjectServiceAttributeOverridesTypedParameterResolution () : void {
            $container = new Container();

            $serviceKey = "special.container";
            $special = $container->fork();
            $container->bindSingleton($serviceKey, fn () => $special);

            $invoker = $container->createInvoker();

            $result = $invoker->call(function (#[\Wingman\Synapse\Attributes\Inject(service: "special.container")] Container $resolvedContainer) use ($special) : bool {
                return $resolvedContainer === $special;
            });

            $this->assertTrue($result === true, "Inject(service) should override typed parameter resolution.");
        }
    }
?>