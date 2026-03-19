<?php
    /**
     * Project Name:    Wingman Synapse - Invoker Bridge And Graph Coverage Tests
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
    use Exception;
    use InvalidArgumentException;
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Synapse\Bridge\PSR\ContainerExceptionInterface as BridgeContainerExceptionInterface;
    use Wingman\Synapse\Bridge\PSR\ContainerInterface as BridgeContainerInterface;
    use Wingman\Synapse\Bridge\PSR\NotFoundException;
    use Wingman\Synapse\Bridge\PSR\NotFoundExceptionInterface as BridgeNotFoundExceptionInterface;
    use Wingman\Synapse\Container;
    use Wingman\Synapse\Enums\Signal;
    use Wingman\Synapse\GraphAnalyser;

    /**
     * Thorough tests for remaining invoker, graph print, enum and bridge compatibility branches.
     */
    class InvokerBridgeAndGraphCoverageTest extends Test {
        #[Group("Invoker")]
        #[Define(name: "useScope() - Enters And Exits Target Scope", description: "Invoker should enter requested scope for call and exit it afterwards.")]
        public function testUseScopeEntersAndExitsTargetScope () : void {
            $container = new Container();
            $container->registerScope("request");
            $buildCount = 0;

            $container->bind(ArrayObject::class, function () use (&$buildCount) {
                $buildCount++;
                $service = new ArrayObject();
                $service[0] = $buildCount;
                return $service;
            }, ["scope" => "request"]);

            $callable = function (ArrayObject $service) : int {
                return $service[0];
            };

            $invoker = $container->createInvoker()->useScope("request");
            $first = $invoker->call($callable);
            $second = $invoker->call($callable);

            $this->assertTrue($first === 1, "First scoped invocation should resolve first scoped instance.");
            $this->assertTrue($second === 2, "Scope should be exited after each call, causing scoped rebuild on next invocation.");
        }

        #[Group("Invoker")]
        #[Define(name: "call() - Resolves Array And String Callable Forms", description: "Invoker should resolve function-name and [class, method] callable contexts.")]
        public function testCallResolvesArrayAndStringCallableForms () : void {
            $container = new Container();

            $functionResult = $container->createInvoker()->call("php_sapi_name");

            $class = get_class(new class {
                public static function compute (int $left = 1, int $right = 2) : int {
                    return $left + $right;
                }
            });

            $methodResult = $container->createInvoker()->call([$class, "compute"]);

            $this->assertTrue(is_string($functionResult) && $functionResult !== "", "String function context should resolve and execute.");
            $this->assertTrue($methodResult === 3, "Array callable context should resolve static methods.");
        }

        #[Group("Invoker")]
        #[Define(name: "useExtraParams() - Throws For Positional Arrays", description: "Invoker should reject positional arrays in useExtraParams().")]
        public function testUseExtraParamsThrowsForPositionalArrays () : void {
            $container = new Container();
            $invoker = $container->createInvoker();
            $thrown = false;

            try {
                $invoker->useExtraParams(["x", "y"]);
            }
            catch (Exception $error) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "useExtraParams() should throw for positional arrays.");
        }

        #[Group("Graph")]
        #[Define(name: "printGraph() - Strict Output", description: "GraphAnalyser::printGraph() should emit deterministic output for a fixed graph.")]
        public function testPrintGraphOutputsStrictText () : void {
            $graph = [
                "A" => ["B", "C"],
                "B" => [],
            ];

            $analyser = new GraphAnalyser($graph);

            ob_start();
            $analyser->printGraph();
            $printed = ob_get_clean();

            $expected = "A depends on: B, C" . PHP_EOL
                . "B depends on: " . PHP_EOL;

            $this->assertTrue($printed === $expected, "printGraph() should output exact expected lines in insertion order.");
        }

        #[Group("Enum")]
        #[Define(name: "Signal::resolve() - Handles String And Enum", description: "Signal resolver should accept both string values and existing enum instances.")]
        public function testSignalResolveHandlesStringAndEnum () : void {
            $resolvedFromString = Signal::resolve("synapse.bound");
            $resolvedFromEnum = Signal::resolve(Signal::RESOLVED);

            $this->assertTrue($resolvedFromString === Signal::BOUND, "Signal::resolve() should map string values to enum cases.");
            $this->assertTrue($resolvedFromEnum === Signal::RESOLVED, "Signal::resolve() should pass through enum inputs unchanged.");
        }

        #[Group("Bridge")]
        #[Define(name: "PSR Bridge Interfaces - Match Current Environment", description: "Bridge interface inheritance should reflect whether psr/container interfaces exist in current runtime.")]
        public function testPsrBridgeInterfacesMatchCurrentEnvironment () : void {
            $hasPsrContainer = interface_exists("Psr\\Container\\ContainerInterface");
            $hasPsrContainerException = interface_exists("Psr\\Container\\ContainerExceptionInterface");
            $hasPsrNotFoundException = interface_exists("Psr\\Container\\NotFoundExceptionInterface");

            $containerBridgeExtends = is_subclass_of(BridgeContainerInterface::class, "Psr\\Container\\ContainerInterface");
            $exceptionBridgeExtends = is_subclass_of(BridgeContainerExceptionInterface::class, "Psr\\Container\\ContainerExceptionInterface");
            $notFoundBridgeExtends = is_subclass_of(BridgeNotFoundExceptionInterface::class, "Psr\\Container\\NotFoundExceptionInterface");

            $this->assertTrue($containerBridgeExtends === $hasPsrContainer, "Container bridge inheritance should match current environment.");
            $this->assertTrue($exceptionBridgeExtends === $hasPsrContainerException, "ContainerException bridge inheritance should match current environment.");
            $this->assertTrue($notFoundBridgeExtends === $hasPsrNotFoundException, "NotFound bridge inheritance should match current environment.");
        }

        #[Group("Bridge")]
        #[Define(name: "PSR NotFound Contract - Strict Missing Identifier", description: "Strict-mode missing identifiers should throw a bridge exception implementing NotFoundExceptionInterface.")]
        public function testPsrNotFoundContractForStrictMissingIdentifier () : void {
            $container = new Container();
            $container->setStrict(true);

            $thrown = null;

            try {
                $container->get("missing.service");
            }
            catch (\Throwable $error) {
                $thrown = $error;
            }

            $this->assertTrue($thrown instanceof BridgeNotFoundExceptionInterface, "Strict missing identifier should throw BridgeNotFoundExceptionInterface.");
            $this->assertTrue($thrown instanceof BridgeContainerExceptionInterface, "NotFound exception should also satisfy BridgeContainerExceptionInterface.");
            $this->assertTrue($thrown instanceof NotFoundException, "Strict missing identifier should throw concrete NotFoundException.");
        }

        #[Group("Bridge")]
        #[Define(name: "PSR ContainerException Contract - Resolution Failure", description: "Known identifiers that fail during construction should throw a bridge container exception.")]
        public function testPsrContainerExceptionContractForResolutionFailure () : void {
            $container = new Container();

            $failingClass = get_class(new class ("") {
                public function __construct (string $name) {}
            });

            $container->bind($failingClass, $failingClass);

            $thrown = null;

            try {
                $container->get($failingClass);
            }
            catch (\Throwable $error) {
                $thrown = $error;
            }

            $this->assertTrue($thrown instanceof BridgeContainerExceptionInterface, "Resolution failure should throw BridgeContainerExceptionInterface.");
            $this->assertTrue(!($thrown instanceof BridgeNotFoundExceptionInterface), "Known identifier resolution failures should not be reported as not-found.");
        }
    }
?>