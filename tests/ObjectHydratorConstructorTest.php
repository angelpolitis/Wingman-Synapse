<?php
    /**
     * Project Name:    Wingman Synapse - Object Hydrator Constructor Tests
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
    use Wingman\Synapse\ObjectHydrator;

    /**
     * Focused tests for constructor-based hydration.
     */
    class ObjectHydratorConstructorTest extends Test {
        #[Group("Hydration")]
        #[Define(name: "hydrate() — Constructor Parameters", description: "Hydrator should map associative data to constructor parameter names.")]
        public function testHydrateMapsConstructorParameters () : void {
            $class = get_class(new class ("seed", 0) {
                public function __construct (
                    public string $name,
                    public int $age
                ) {}
            });

            $hydrator = new ObjectHydrator();
            $dto = $hydrator->hydrate($class, ["name" => "Angel", "age" => 35]);

            $this->assertTrue($dto->name === "Angel", "hydrate() should map constructor scalar parameters by name.");
            $this->assertTrue($dto->age === 35, "hydrate() should map all provided constructor parameters.");
        }

        #[Group("Hydration")]
        #[Define(name: "hydrate() — Uses Constructor Defaults", description: "Optional constructor values should keep defaults when omitted.")]
        public function testHydrateUsesConstructorDefaults () : void {
            $class = get_class(new class ("seed", 10) {
                public function __construct (
                    public string $name,
                    public int $ttl = 60
                ) {}
            });

            $hydrator = new ObjectHydrator();
            $dto = $hydrator->hydrate($class, ["name" => "cache"]);

            $this->assertTrue($dto->ttl === 60, "hydrate() should honour optional constructor defaults when values are omitted.");
        }

        #[Group("Hydration")]
        #[Define(name: "hydrate() — Throws For Missing Required Parameters", description: "Missing required constructor params should throw RuntimeException.")]
        public function testHydrateThrowsForMissingRequiredParameters () : void {
            $class = get_class(new class ("seed") {
                public function __construct (public string $required) {}
            });

            $hydrator = new ObjectHydrator();
            $thrown = false;

            try {
                $hydrator->hydrate($class, []);
            }
            catch (RuntimeException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "hydrate() should throw when required constructor parameters are missing.");
        }
    }
?>