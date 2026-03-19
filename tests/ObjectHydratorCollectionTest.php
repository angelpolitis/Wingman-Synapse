<?php
    /**
     * Project Name:    Wingman Synapse - Object Hydrator Collection Tests
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
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Synapse\ObjectHydrator;

    /**
     * Focused tests for property and collection hydration paths.
     */
    class ObjectHydratorCollectionTest extends Test {
        #[Group("Hydration")]
        #[Define(name: "hydrate() — Populates Public Properties Without Constructor", description: "Property-based DTOs should be assigned directly when no constructor exists.")]
        public function testHydratePopulatesPublicPropertiesWithoutConstructor () : void {
            $class = get_class(new class {
                public string $firstName = "";
                public int $age = 0;
            });

            $hydrator = new ObjectHydrator();
            $dto = $hydrator->hydrate($class, ["firstName" => "Angel", "age" => 35]);

            $this->assertTrue($dto->firstName === "Angel", "hydrate() should set public properties when constructor is absent.");
            $this->assertTrue($dto->age === 35, "hydrate() should assign all matching public properties.");
        }

        #[Group("Hydration")]
        #[Define(name: "hydrateMany() — Hydrates Collections", description: "hydrateMany() should map each row into a typed object in order.")]
        public function testHydrateManyHydratesCollections () : void {
            $class = get_class(new class ("seed") {
                public function __construct (public string $name) {}
            });

            $hydrator = new ObjectHydrator();
            $rows = $hydrator->hydrateMany($class, [
                ["name" => "one"],
                ["name" => "two"],
            ]);

            $this->assertTrue(count($rows) === 2, "hydrateMany() should return one object per data row.");
            $this->assertTrue($rows[0]->name === "one" && $rows[1]->name === "two", "hydrateMany() should preserve row-to-object ordering.");
        }
    }
?>