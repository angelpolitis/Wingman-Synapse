<?php
    /**
     * Project Name:    Wingman Synapse - Cortex Bridge And Configuration Tests
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 19 2026
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
    use Wingman\Synapse\Bridge\Cortex\Configuration as CortexBridge;
    use Wingman\Synapse\Container;

    /**
     * Tests Cortex bridge compatibility and container constructor hydration.
     */
    class CortexBridgeAndConfigurationTest extends Test {
        #[Group("Bridge")]
        #[Define(name: "Cortex Bridge - Registry Accessors Return Safe Defaults", description: "Bridge statics should return safe defaults when Cortex is unavailable, while preserving API shape when installed.")]
        public function testCortexBridgeRegistryAccessorsReturnSafeDefaults () : void {
            $result = CortexBridge::find("nonexistent_" . uniqid());

            $this->assertTrue($result === null || $result instanceof CortexBridge, "find() should return null or a configuration instance.");
            $this->assertTrue(is_bool(CortexBridge::exists("nonexistent_" . uniqid())), "exists() should return a boolean.");
            $this->assertTrue(is_array(CortexBridge::getAll()), "getAll() should return an array.");
            $this->assertTrue(is_array(CortexBridge::getAllNames()), "getAllNames() should return an array.");
        }

        #[Group("Bridge")]
        #[Define(name: "Cortex Bridge - Fluent Instance Methods", description: "Bridge instance methods should preserve fluent behaviour regardless of whether Cortex is installed.")]
        public function testCortexBridgeInstanceMethodsAreFluent () : void {
            $configuration = CortexBridge::fromIterable(["synapse.container.strict" => true], "synapse.test");

            $this->assertTrue($configuration instanceof CortexBridge, "fromIterable() should return a configuration instance.");
            $this->assertTrue($configuration->captureObject(new \stdClass(), "capture") === $configuration, "captureObject() should be fluent.");
            $this->assertTrue($configuration->restoreObject(new \stdClass(), "restore") === $configuration, "restoreObject() should be fluent.");
        }

        #[Group("Container")]
        #[Define(name: "Container Constructor - Hydrates From Array", description: "Container constructor should hydrate strict and injection options from flat dot-notation arrays.")]
        public function testContainerConstructorHydratesFromArray () : void {
            $container = new Container([
                "synapse.container.inject.attributesOnly" => true,
                "synapse.container.inject.protected" => true,
                "synapse.container.strict" => true,
            ]);

            $thrown = false;

            try {
                $container->get("stdClass");
            }
            catch (RuntimeException $exception) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "Strict mode should be enabled by constructor hydration from array config.");
        }

        #[Group("Container")]
        #[Define(name: "Container Constructor - Accepts Configuration Instance", description: "Container constructor should accept bridge Configuration instances and hydrate options from them.")]
        public function testContainerConstructorAcceptsConfigurationInstance () : void {
            $configuration = CortexBridge::fromIterable([
                "synapse.container.strict" => true,
            ], "synapse.container");

            $container = new Container($configuration);
            $thrown = false;

            try {
                $container->get("stdClass");
            }
            catch (RuntimeException $exception) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "Strict mode should be enabled when constructed from a bridge configuration instance.");
        }
    }
?>