<?php
    /**
     * Project Name:    Wingman Synapse - Graph Analyser Tests
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
    use Wingman\Synapse\GraphAnalyser;

    /**
     * Tests dependency graph inspection and cycle detection.
     */
    class GraphAnalyserTest extends Test {
        #[Group("Graph")]
        #[Define(name: "getDependencies() — Returns Direct Dependencies", description: "Graph analyser should return direct dependencies for a class.")]
        public function testGetDependenciesReturnsDirectDependencies () : void {
            $graph = [
                "A" => ["B", "C"],
                "B" => ["C"],
                "C" => [],
            ];

            $analyser = new GraphAnalyser($graph);
            $deps = $analyser->getDependencies("A");

            $this->assertTrue($deps === ["B", "C"], "getDependencies() should return the direct dependency list in stored order.");
        }

        #[Group("Graph")]
        #[Define(name: "getDependents() — Returns Reverse Edges", description: "Graph analyser should list classes that depend on a given class.")]
        public function testGetDependentsReturnsReverseEdges () : void {
            $graph = [
                "A" => ["B"],
                "C" => ["B"],
                "B" => [],
            ];

            $analyser = new GraphAnalyser($graph);
            $dependents = $analyser->getDependents("B");
            sort($dependents);

            $this->assertTrue($dependents === ["A", "C"], "getDependents() should return all direct reverse dependencies.");
        }

        #[Group("Graph")]
        #[Define(name: "detectCircularDependencies() — Detects Cycles", description: "Graph analyser should detect circular paths using DFS colouring.")]
        public function testDetectCircularDependenciesDetectsCycles () : void {
            $graph = [
                "A" => ["B"],
                "B" => ["C"],
                "C" => ["A"],
            ];

            $analyser = new GraphAnalyser($graph);
            $cycles = $analyser->detectCircularDependencies();

            $this->assertTrue(count($cycles) >= 1, "Cycle detection should return at least one cycle for cyclic graphs.");
            $this->assertTrue($cycles[0][0] === $cycles[0][count($cycles[0]) - 1], "Returned cycle paths should be closed (first node equals last node).");
        }

        #[Group("Graph")]
        #[Define(name: "detectCircularDependencies() — Returns Empty For DAG", description: "Acyclic graphs should produce no cycles.")]
        public function testDetectCircularDependenciesReturnsEmptyForDag () : void {
            $graph = [
                "A" => ["B", "C"],
                "B" => ["D"],
                "C" => [],
                "D" => [],
            ];

            $analyser = new GraphAnalyser($graph);
            $cycles = $analyser->detectCircularDependencies();

            $this->assertTrue($cycles === [], "Cycle detection should return an empty array for acyclic graphs.");
        }
    }
?>