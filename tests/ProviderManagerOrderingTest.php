<?php
    /**
     * Project Name:    Wingman Synapse - Provider Manager Ordering Tests
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
    use Wingman\Synapse\Provider;
    use Wingman\Synapse\ProviderManager;

    /**
     * Focused tests for deterministic provider ordering.
     */
    class ProviderManagerOrderingTest extends Test {
        #[Group("Provider")]
        #[Define(name: "boot() — Orders Providers By Dependency Then Priority", description: "Providers should be processed by dependency, then priority, then class name.")]
        public function testBootOrdersProvidersByDependencyThenPriority () : void {
            $container = new Container();
            $manager = new ProviderManager($container);
            $log = [];

            $providerA = new class ($container, $log) extends Provider {
                private array $log;
                public function __construct (Container $container, array &$log) { parent::__construct($container); $this->log =& $log; }
                public function getPriority () : int { return 1; }
                public function register () : void { $this->log[] = "A.register"; }
                public function boot () : void { $this->log[] = "A.boot"; }
            };

            $providerB = new class ($container, $log) extends Provider {
                private array $log;
                public function __construct (Container $container, array &$log) { parent::__construct($container); $this->log =& $log; }
                public function getPriority () : int { return 10; }
                public function register () : void { $this->log[] = "B.register"; }
                public function boot () : void { $this->log[] = "B.boot"; }
            };

            $providerC = new class ($container, $log, $providerA) extends Provider {
                private array $log;
                private Provider $dependency;
                public function __construct (Container $container, array &$log, Provider $dependency) { parent::__construct($container); $this->log =& $log; $this->dependency = $dependency; }
                public function getDependencies () : array { return [get_class($this->dependency)]; }
                public function register () : void { $this->log[] = "C.register"; }
                public function boot () : void { $this->log[] = "C.boot"; }
            };

            $manager->addProviders([$providerA, $providerB, $providerC])->boot();

            $this->assertTrue($log === ["B.register", "A.register", "C.register", "B.boot", "A.boot", "C.boot"], "Provider ordering should be deterministic and dependency-safe.");
        }
    }
?>