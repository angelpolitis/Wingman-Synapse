<?php
    /**
     * Project Name:    Wingman Synapse - Graph Analyser
     * Created by:      Angel Politis
     * Creation Date:   Mar 18 2026
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse namespace.
    namespace Wingman\Synapse;

    # Import the following classes to the current scope.
    use Wingman\Synapse\Enums\NodeState;

    /**
     * Analyses a precomputed dependency graph extracted from the container.
     *
     * GraphAnalyser is a stateless utility that accepts a dependency graph array
     * (a map of class-name to a list of dependency class names) and exposes
     * read-only analysis operations: printing, querying dependencies and dependents,
     * and detecting circular references via depth-first search.
     *
     * @package Wingman\Synapse
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class GraphAnalyser {
        /**
         * The dependency graph to analyse.
         * @var array<string, string[]>
         */
        private array $graph;

        /**
         * Creates a new GraphAnalyser.
         *
         * @param array<string, string[]> $graph A map of class-name to dependency class names.
         */
        public function __construct (array $graph) {
            $this->graph = $graph;
        }

        /**
         * Get all direct dependencies of the given class.
         *
         * @param string $class Fully-qualified class name.
         * @return string[] Class names this class directly depends on.
         */
        public function getDependencies (string $class) : array {
            return $this->graph[$class] ?? [];
        }

        /**
         * Get all classes that directly depend on the given class.
         * @param string $class Fully-qualified class name.
         * @return string[] Class names that list $class as a direct dependency.
         */
        public function getDependents (string $class) : array {
            $dependents = [];

            foreach ($this->graph as $consumer => $dependencies) {
                if (in_array($class, $dependencies, true)) {
                    $dependents[] = $consumer;
                }
            }

            return $dependents;
        }

        /**
         * Print a human-readable summary of the dependency graph to stdout.
         */
        public function printGraph () : void {
            foreach ($this->graph as $service => $deps) {
                echo $service . " depends on: " . implode(", ", $deps) . PHP_EOL;
            }
        }

        /**
         * Detect all circular dependency cycles in the graph using depth-first search.
         *
         * Each returned cycle is an array of class names forming the loop, with
         * the first and last element being the same node (inclusive).
         *
         * @return array<int, string[]> All detected cycles.
         */
        public function detectCircularDependencies () : array {
            $state  = [];
            $cycles = [];

            $dfs = function (string $node, array $path) use (&$dfs, &$state, &$cycles) : void {
                $nodeState = $state[$node] ?? null;

                if ($nodeState === NodeState::Black) {
                    return;
                }

                if ($nodeState === NodeState::Gray) {
                    $start = array_search($node, $path, true);
                    $cycles[] = array_merge(array_slice($path, $start), [$node]);
                    return;
                }

                $state[$node] = NodeState::Gray;
                $path[] = $node;

                foreach ($this->graph[$node] ?? [] as $dep) {
                    $dfs($dep, $path);
                }

                $state[$node] = NodeState::Black;
            };

            foreach (array_keys($this->graph) as $node) {
                if (!isset($state[$node])) {
                    $dfs($node, []);
                }
            }

            return $cycles;
        }
    }
?>