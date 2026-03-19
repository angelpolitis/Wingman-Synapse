<?php
    /**
     * Project Name:    Wingman Synapse - Config Loader Tests
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
    use Wingman\Synapse\ConfigLoader;
    use Wingman\Synapse\Container;

    /**
     * Tests JSON configuration loading and error handling.
     */
    class ConfigLoaderTest extends Test {
        #[Group("Config")]
        #[Define(name: "load() — Throws For Missing File", description: "Loading a non-existent JSON file should throw RuntimeException.")]
        public function testLoadThrowsForMissingFile () : void {
            $loader = new ConfigLoader(new Container());
            $thrown = false;

            try {
                $loader->load(sys_get_temp_dir() . "/synapse_missing_" . uniqid() . ".json");
            }
            catch (RuntimeException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "ConfigLoader::load() should throw when the file does not exist.");
        }

        #[Group("Config")]
        #[Define(name: "load() — Throws For Invalid JSON", description: "Invalid JSON payloads should be rejected.")]
        public function testLoadThrowsForInvalidJson () : void {
            $loader = new ConfigLoader(new Container());
            $file = sys_get_temp_dir() . "/synapse_invalid_" . uniqid() . ".json";
            file_put_contents($file, "{not-valid-json");

            $thrown = false;

            try {
                $loader->load($file);
            }
            catch (RuntimeException $e) {
                $thrown = true;
            }
            finally {
                @unlink($file);
            }

            $this->assertTrue($thrown, "ConfigLoader::load() should throw for invalid JSON content.");
        }

        #[Group("Config")]
        #[Define(name: "load() — Applies Services, Aliases, Tags And Parameters", description: "A full config should register all supported sections into the container.")]
        public function testLoadAppliesServicesAliasesTagsAndParameters () : void {
            $container = new Container();
            $loader = new ConfigLoader($container);

            $serviceClass = get_class(new class ("seed") {
                public function __construct (public string $token) {}
            });

            $config = [
                "scopes" => ["request"],
                "services" => [
                    "app.token" => [
                        "concrete" => $serviceClass,
                        "singleton" => true,
                        "scope" => "request",
                        "tags" => ["workers" => 7],
                    ],
                ],
                "tags" => [
                    "workers" => ["app.token" => 7],
                ],
                "parameters" => [
                    $serviceClass => ["token" => "abc-123"],
                ],
                "aliases" => [
                    "token.alias" => "app.token",
                ],
            ];

            $file = sys_get_temp_dir() . "/synapse_config_" . uniqid() . ".json";
            file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT));

            try {
                $loader->load($file);
                $container->enterScope("request");
                $service = $container->get("token.alias");
                $tagged = $container->getByTag("workers");
                $container->exitScope();
            }
            finally {
                @unlink($file);
            }

            $this->assertTrue($service->token === "abc-123", "Configured constructor parameter should be injected.");
            $this->assertTrue(count($tagged) >= 1, "Configured tag should resolve at least one service.");
        }
    }
?>