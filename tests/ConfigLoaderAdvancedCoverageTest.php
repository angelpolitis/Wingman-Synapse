<?php
    /**
     * Project Name:    Wingman Synapse - Config Loader Advanced Coverage Tests
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
    use Wingman\Synapse\ConfigLoader;
    use Wingman\Synapse\Container;

    /**
     * Thorough tests for ConfigLoader branches and service mode loading.
     */
    class ConfigLoaderAdvancedCoverageTest extends Test {
        #[Group("Config")]
        #[Define(name: "load() - Supports Singleton Boolean And Mode Values", description: "ConfigLoader should support both singleton boolean and explicit mode values.")]
        public function testLoadSupportsSingletonBooleanAndModeValues () : void {
            $container = new Container();
            $loader = new ConfigLoader($container);

            $boolSingletonClass = get_class(new class {
                public int $value = 1;
            });

            $modeSingletonClass = get_class(new class {
                public int $value = 2;
            });

            $transientClass = get_class(new class {
                public int $value = 3;
            });

            $config = [
                "services" => [
                    "singleton.bool" => [
                        "concrete" => $boolSingletonClass,
                        "singleton" => true,
                    ],
                    "singleton.mode" => [
                        "concrete" => $modeSingletonClass,
                        "mode" => "singleton",
                    ],
                    "transient.mode" => [
                        "concrete" => $transientClass,
                        "mode" => "transient",
                    ],
                ],
            ];

            $file = sys_get_temp_dir() . "/synapse_cfg_modes_" . uniqid() . ".json";
            file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT));

            try {
                $loader->load($file);
            }
            finally {
                @unlink($file);
            }

            $boolOne = $container->get("singleton.bool");
            $boolTwo = $container->get("singleton.bool");
            $modeOne = $container->get("singleton.mode");
            $modeTwo = $container->get("singleton.mode");
            $transientOne = $container->get("transient.mode");
            $transientTwo = $container->get("transient.mode");

            $this->assertTrue($boolOne === $boolTwo, "singleton=true should create singleton lifetime.");
            $this->assertTrue($modeOne === $modeTwo, "mode=singleton should create singleton lifetime.");
            $this->assertTrue($transientOne !== $transientTwo, "mode=transient should create transient lifetime.");
        }

        #[Group("Config")]
        #[Define(name: "load() - Applies Constructor And Property Injection Maps", description: "ConfigLoader should populate constructor and property injection rules from JSON.")]
        public function testLoadAppliesConstructorAndPropertyInjectionMaps () : void {
            $container = new Container();
            $loader = new ConfigLoader($container);

            $primaryDependencyClass = ArrayObject::class;
            $secondaryDependencyClass = SplObjectStorage::class;

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

            $config = [
                "services" => [
                    "dep.primary" => [
                        "concrete" => $primaryDependencyClass,
                        "mode" => "singleton",
                    ],
                    "dep.secondary" => [
                        "concrete" => $secondaryDependencyClass,
                        "mode" => "singleton",
                    ],
                    $consumerClass => [
                        "concrete" => $consumerClass,
                        "constructorInjection" => [
                            "constructorDependency" => ["service" => "dep.primary"],
                        ],
                        "propertyInjection" => [
                            "propertyDependency" => ["service" => "dep.secondary"],
                        ],
                    ],
                ],
                "parameters" => [
                    $consumerClass => [
                        "name" => "configured-name",
                    ],
                ],
            ];

            $file = sys_get_temp_dir() . "/synapse_cfg_injection_" . uniqid() . ".json";
            file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT));

            try {
                $loader->load($file);
            }
            finally {
                @unlink($file);
            }

            $consumer = $container->get($consumerClass);

            $this->assertTrue($consumer->name === "configured-name", "Parameter map should provide scalar constructor argument.");
            $this->assertTrue($consumer->constructorDependency instanceof ArrayObject, "Constructor injection map should be honoured.");
            $this->assertTrue($consumer->propertyDependency instanceof SplObjectStorage, "Property injection map should be honoured.");
        }

        #[Group("Config")]
        #[Define(name: "load() - Applies Contextual Overrides", description: "ConfigLoader should load contextual map and resolve consumer-specific dependencies.")]
        public function testLoadAppliesContextualOverrides () : void {
            $container = new Container();
            $loader = new ConfigLoader($container);

            $consumerClass = get_class(new class (new ArrayObject()) {
                public function __construct (public Countable $dependency) {}
            });

            $config = [
                "services" => [
                    Countable::class => ["concrete" => ArrayObject::class],
                    SplObjectStorage::class => ["concrete" => SplObjectStorage::class],
                    $consumerClass => ["concrete" => $consumerClass],
                ],
                "contextual" => [
                    $consumerClass => [
                        Countable::class => SplObjectStorage::class,
                    ],
                ],
            ];

            $file = sys_get_temp_dir() . "/synapse_cfg_contextual_" . uniqid() . ".json";
            file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT));

            try {
                $loader->load($file);
                $resolved = $container->get($consumerClass);
            }
            finally {
                @unlink($file);
            }

            $this->assertTrue($resolved->dependency instanceof SplObjectStorage, "Contextual map should override dependency for the configured consumer.");
        }
    }
?>