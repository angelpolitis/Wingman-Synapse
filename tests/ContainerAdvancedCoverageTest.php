<?php
    /**
     * Project Name:    Wingman Synapse - Container Advanced Coverage Tests
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
    use ArrayObject;
    use Countable;
    use SplObjectStorage;
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Synapse\Attributes\Inject;
    use Wingman\Synapse\Container;
    use Wingman\Synapse\Tests\Fixtures\GraphConsumer;
    use Wingman\Synapse\Tests\Fixtures\GraphScopedConsumer;

    /**
     * Thorough tests for advanced container lifecycle and configuration APIs.
     */
    class ContainerAdvancedCoverageTest extends Test {
        #[Group("Container")]
        #[Define(name: "Conditional Binds - Honour Conditions", description: "bindIf variants should bind only when the condition is true.")]
        public function testConditionalBindsHonourConditions () : void {
            $container = new Container();

            $container->bindIf(false, "never.bound", fn () => (object) []);
            $container->bindIf(true, "always.bound", fn () => (object) []);
            $container->bindSingletonIf(true, "single.cond", fn () => (object) ["k" => 1]);
            $container->bindLazyIf(true, "lazy.cond", fn () => (object) ["k" => 2]);
            $container->bindTransientIf(true, "transient.cond", fn () => (object) ["k" => 3]);

            $this->assertTrue(!$container->hasBinding("never.bound"), "bindIf(false) should not register the binding.");
            $this->assertTrue($container->hasBinding("always.bound"), "bindIf(true) should register the binding.");
            $this->assertTrue($container->get("single.cond") === $container->get("single.cond"), "bindSingletonIf(true) should produce singleton behaviour.");
            $this->assertTrue($container->get("lazy.cond") === $container->get("lazy.cond"), "bindLazyIf(true) should cache lazy singleton instance.");
            $this->assertTrue($container->get("transient.cond") !== $container->get("transient.cond"), "bindTransientIf(true) should produce fresh instances.");
        }

        #[Group("Container")]
        #[Define(name: "Lifecycle Callbacks - Fire In Order", description: "onBind, onBeforeResolving, onResolving and onResolved callbacks should all fire in deterministic order.")]
        public function testLifecycleCallbacksFireInOrder () : void {
            $container = new Container();
            $events = [];

            $container->onBind("cb.service", function () use (&$events) {
                $events[] = "bind";
            });

            $container->onBeforeResolving(function (string $abstract) use (&$events) {
                if ($abstract === "cb.service") {
                    $events[] = "before";
                }
            });

            $container->onResolving("cb.service", function () use (&$events) {
                $events[] = "resolving";
            });

            $container->onResolved("cb.service", function () use (&$events) {
                $events[] = "resolved";
            });

            $container->bind("cb.service", fn () => (object) ["ok" => true]);
            $container->get("cb.service");

            $this->assertTrue($events === ["bind", "before", "resolving", "resolved"], "Container callbacks should fire in expected order.");
        }

        #[Group("Container")]
        #[Define(name: "extend() And rebind() - Decorate And Replace", description: "extend() should decorate resolved instances and rebind() should replace prior registration state.")]
        public function testExtendAndRebindDecorateAndReplace () : void {
            $container = new Container();

            $container->bindSingleton("sample", fn () => (object) ["value" => 5]);
            $container->extend("sample", function (object $instance) {
                $instance->value += 2;
                return $instance;
            });

            $decorated = $container->get("sample");
            $container->rebind("sample", fn () => (object) ["value" => 10], ["mode" => \Wingman\Synapse\Enums\BindingMode::Singleton]);
            $rebound = $container->get("sample");

            $this->assertTrue($decorated->value === 7, "extend() should modify resolved instance.");
            $this->assertTrue($rebound->value === 10, "rebind() should replace previous binding configuration.");
        }

        #[Group("Container")]
        #[Define(name: "Injection Maps And Parameters - Resolve Correctly", description: "Constructor and property injection maps with scalar parameters should be applied during resolution.")]
        public function testInjectionMapsAndParametersResolveCorrectly () : void {
            $container = new Container();

            $consumerClass = get_class(new class ("", new ArrayObject()) {
                public string $name;
                public Countable $constructorDependency;
                #[Inject]
                public ?Countable $propertyDependency = null;

                public function __construct (string $name, Countable $constructorDependency) {
                    $this->name = $name;
                    $this->constructorDependency = $constructorDependency;
                }
            });

            $container->bindSingleton("dep.first", ArrayObject::class);
            $container->bindSingleton("dep.second", SplObjectStorage::class);
            $container->bind($consumerClass, $consumerClass);
            $container->setParameter("name", "injected-name");
            $container->addConstructorInjection($consumerClass, "constructorDependency", "dep.first", null);
            $container->addPropertyInjection($consumerClass, "propertyDependency", "dep.second", null);

            $consumer = $container->get($consumerClass);

            $this->assertTrue($consumer->name === "injected-name", "setParameter() should provide constructor scalar values.");
            $this->assertTrue($consumer->constructorDependency instanceof ArrayObject, "addConstructorInjection() should override constructor dependency.");
            $this->assertTrue($consumer->propertyDependency instanceof SplObjectStorage, "addPropertyInjection() should override property dependency.");
        }

        #[Group("Container")]
        #[Define(name: "createProxy() - Defers First Resolution", description: "Lazy proxy should defer target service creation until first method call.")]
        public function testCreateProxyDefersFirstResolution () : void {
            $container = new Container();
            $serviceClass = get_class(new class {
                private int $calls = 0;

                public function ping () : int {
                    $this->calls++;
                    return $this->calls;
                }
            });

            $container->bindSingleton("proxy.service", $serviceClass);

            $proxy = $container->createProxy("proxy.service");

            $first = $proxy->ping();
            $second = $proxy->ping();

            $this->assertTrue(is_object($proxy), "createProxy() should return a proxy object.");
            $this->assertTrue($first === 1, "First proxy call should forward method invocation to target service.");
            $this->assertTrue($second === 2, "Subsequent proxy calls should reuse the same underlying singleton target.");
        }

        #[Group("Container")]
        #[Define(name: "Graph APIs - Return Scoped And Global Graphs", description: "Container graph APIs should expose global/scoped graph information and print deterministic output.")]
        public function testGraphApisReturnScopedAndGlobalGraphs () : void {
            $container = new Container();

            $consumerClass = GraphConsumer::class;
            $scopedConsumerClass = GraphScopedConsumer::class;

            $dependencyClass = ArrayObject::class;

            $container->bind($dependencyClass, ArrayObject::class);
            $container->bind($consumerClass, $consumerClass);
            $container->bind($scopedConsumerClass, $scopedConsumerClass, ["scope" => "request"]);
            $container->registerScope("request");

            $container->get($consumerClass);
            $globalGraph = $container->getDependencyGraph();
            $globalDependencies = $container->getDependencies($consumerClass);
            $globalDependents = $container->getDependents($dependencyClass);

            $container->enterScope("request");
            $container->get($scopedConsumerClass);
            $scopedGraph = $container->getScopedDependencyGraph("request");
            $container->exitScope();

            ob_start();
            $container->printDependencyGraph();
            $printed = ob_get_clean();

            $expectedLine = $consumerClass . " depends on: " . $dependencyClass . PHP_EOL;

            $this->assertTrue($container->getDefaultScope() === "global", "Default container scope should be global.");
            $this->assertTrue(isset($globalGraph[$consumerClass]), "Global dependency graph should contain the consumer node.");
            $this->assertTrue(in_array($dependencyClass, $globalDependencies, true), "getDependencies() should list constructor dependency.");
            $this->assertTrue(in_array($consumerClass, $globalDependents, true), "getDependents() should list reverse edge.");
            $this->assertTrue(isset($scopedGraph[$scopedConsumerClass]), "Scoped dependency graph should contain consumer node after scoped resolution.");
            $this->assertTrue(str_contains($printed, $expectedLine), "printDependencyGraph() output should include exact dependency line.");
        }

        #[Group("Container")]
        #[Define(name: "getByTag() - Reuses Lazy Scoped Cache", description: "Lazy scoped services resolved via getByTag() should be cached per scope, matching get() semantics.")]
        public function testGetByTagReusesLazyScopedCache () : void {
            $container = new Container();
            $container->registerScope("request");

            $buildCount = 0;

            $container->bind("tag.lazy.scoped", function () use (&$buildCount) {
                $buildCount++;
                return (object) ["build" => $buildCount];
            }, [
                "mode" => \Wingman\Synapse\Enums\BindingMode::Scoped,
                "lazy" => true,
                "scope" => "request",
                "tags" => ["workers"],
            ]);

            $container->enterScope("request");
            $firstList = $container->getByTag("workers", scope: "request");
            $secondList = $container->getByTag("workers", scope: "request");
            $container->exitScope();

            $this->assertTrue(count($firstList) === 1, "getByTag() should return one tagged service.");
            $this->assertTrue($firstList[0] === $secondList[0], "Lazy scoped tagged service should be reused within the same scope.");
            $this->assertTrue($buildCount === 1, "Lazy scoped tagged service should be built once per scope.");
        }
    }
?>