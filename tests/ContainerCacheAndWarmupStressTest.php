<?php
    /**
     * Project Name:    Wingman Synapse - Container Cache And Warmup Stress Tests
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
    use Wingman\Synapse\Tests\Fixtures\WarmupBindableService;

    /**
     * Edge-case stress tests for cache export/load and warmup pre-scanning.
     */
    class ContainerCacheAndWarmupStressTest extends Test {
        #[Group("Container")]
        #[Define(name: "exportCache()/loadCache() — Persists Serializable State", description: "Cache export should persist serialisable container config and drop closure concretes.")]
        public function testExportAndLoadCachePersistsSerializableState () : void {
            $container = new Container();

            $container->bind("date.service", \stdClass::class);
            $container->bind("closure.service", fn () => (object) ["x" => 1]);
            $container->alias("date.alias", "date.service");

            $cacheFile = sys_get_temp_dir() . "/synapse_cache_" . uniqid() . ".php";

            try {
                $container->exportCache($cacheFile);

                $restored = new Container();
                $restored->loadCache($cacheFile);

                $this->assertTrue($restored->hasBinding("date.service"), "String-concrete binding should survive cache export/load.");
                $this->assertTrue(!$restored->hasBinding("closure.service"), "Closure-concrete binding should be excluded from cache export.");
                $this->assertTrue($restored->get("date.alias") instanceof \stdClass, "Alias mapping should survive cache export/load.");
            }
            finally {
                @unlink($cacheFile);
            }
        }

        #[Group("Container")]
        #[Define(name: "loadCache() — Throws For Missing File", description: "Loading a non-existent cache file should throw RuntimeException.")]
        public function testLoadCacheThrowsForMissingFile () : void {
            $container = new Container();
            $thrown = false;

            try {
                $container->loadCache(sys_get_temp_dir() . "/missing_synapse_cache_" . uniqid() . ".php");
            }
            catch (RuntimeException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "loadCache() should throw when cache file does not exist.");
        }

        #[Group("Container")]
        #[Define(name: "warmup() — Registers Attribute-Driven Bindings", description: "Warmup should pre-scan bound concretes and register discovered attribute bindings.")]
        public function testWarmupRegistersAttributeDrivenBindings () : void {
            $container = new Container();

            $container->bind("synapse.warmup.seed", WarmupBindableService::class);

            $this->assertTrue(!$container->hasBinding("synapse.warmup.bound"), "Attribute-derived binding should not exist before warmup.");

            $container->warmup();

            $this->assertTrue($container->hasBinding("synapse.warmup.bound"), "warmup() should register attribute-derived bindings.");
            $this->assertTrue($container->get("synapse.warmup.bound") instanceof WarmupBindableService, "Attribute-derived abstract should resolve after warmup.");
        }
    }
?>