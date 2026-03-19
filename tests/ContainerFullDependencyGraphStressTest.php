<?php
    /**
     * Project Name:    Wingman Synapse - Container Full Dependency Graph Stress Tests
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
    use Wingman\Synapse\Container;
    use Wingman\Synapse\Tests\Fixtures\GraphCycleA;

    /**
     * Edge-case stress tests for recursive full dependency graph expansion.
     */
    class ContainerFullDependencyGraphStressTest extends Test {
        #[Group("Graph")]
        #[Define(name: "getFullDependencyGraph() — Handles Recursive Cycles", description: "Graph introspection should terminate on circular constructor dependency graphs.")]
        public function testGetFullDependencyGraphHandlesRecursiveCycles () : void {
            $container = new Container();

            $graph = $container->getFullDependencyGraph(GraphCycleA::class);

            $this->assertTrue(isset($graph["constructor"]["b"]["dependency"]), "Graph should include first-level constructor dependency metadata.");
            $this->assertTrue($graph["constructor"]["b"]["dependency"] !== "", "First-level dependency entry should include a class name.");
            $this->assertTrue(is_array($graph["constructor"]["b"]["subgraph"]), "Subgraph expansion should return arrays for recursive dependencies.");
        }

        #[Group("Graph")]
        #[Define(name: "getFullDependencyGraph() — Returns Empty For Unknown Class", description: "Graph introspection should return an empty result for non-existent classes.")]
        public function testGetFullDependencyGraphReturnsEmptyForUnknownClass () : void {
            $container = new Container();

            $graph = $container->getFullDependencyGraph("Wingman\\Synapse\\Tests\\Fixtures\\DefinitelyMissingClass");

            $this->assertTrue($graph === [], "Unknown classes should produce an empty graph.");
        }
    }
?>