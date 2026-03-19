<?php
    /**
     * Project Name:    Wingman Synapse - Provider Manager
     * Created by:      Angel Politis
     * Creation Date:   Mar 18 2026
     * Last Modified:   Mar 19 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Synapse namespace.
    namespace Wingman\Synapse;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Throwable;
    use Wingman\Synapse\Interfaces\ProviderInterface;

    /**
     * Orchestrates provider registration and boot lifecycle.
     *
     * Guarantees:
     * - register() runs once per provider instance.
     * - boot() runs once per provider instance.
     * - Non-deferred providers register first, then non-deferred providers boot.
     * - Deferred providers register+boot on first resolution of any provided abstract.
     * - Provider order is dependency-first, then priority descending, then class name ascending.
     *
     * @package Wingman\Synapse
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ProviderManager {
        /**
         * Container used to register and boot providers.
         * @var Container
         */
        protected Container $container;

        /**
         * Map of abstract identifier => provider class for deferred providers.
         * @var array<string, class-string<ProviderInterface>>
         */
        protected array $deferredByAbstract = [];

        /**
         * Event listeners keyed by event name.
         * @var array<string, callable[]>
         */
        protected array $listeners = [
            "booted" => [],
            "booting" => [],
            "failed" => [],
            "registered" => [],
            "registering" => [],
        ];

        /**
         * Provider classes that already completed boot().
         * @var array<class-string<ProviderInterface>, bool>
         */
        protected array $booted = [];

        /**
         * Provider classes that already completed register().
         * @var array<class-string<ProviderInterface>, bool>
         */
        protected array $registered = [];

        /**
         * Externally declared ordering constraints: dependent => [dependency, ...].
         * Both sides are silently ignored when a provider is not registered.
         * @var array<class-string<ProviderInterface>, class-string<ProviderInterface>[]>
         */
        protected array $externalDependencies = [];

        /**
         * Registered provider instances keyed by provider class name.
         * @var array<class-string<ProviderInterface>, ProviderInterface>
         */
        protected array $providers = [];

        /**
         * Creates a new provider manager.
         * @param Container $container Container used by providers.
         */
        public function __construct (Container $container) {
            $this->container = $container;
            $this->container->onBeforeResolving(fn (string $abstract) => $this->resolveDeferred($abstract));
        }

        /**
         * Boots a provider once.
         * @param class-string<ProviderInterface> $providerClass Provider class name.
         * @throws RuntimeException If booting fails.
         */
        protected function bootProvider (string $providerClass) : void {
            if (isset($this->booted[$providerClass])) {
                return;
            }

            $this->registerProvider($providerClass);
            $provider = $this->providers[$providerClass];
            $this->dispatch("booting", $providerClass, $provider);

            try {
                $provider->boot();
            }
            catch (Throwable $error) {
                $this->dispatch("failed", $providerClass, $provider, $error);
                throw new RuntimeException("Provider '$providerClass' failed during boot().", 0, $error);
            }

            $this->booted[$providerClass] = true;
            $this->dispatch("booted", $providerClass, $provider);
        }

        /**
         * Dispatches lifecycle listeners for the given event.
         * @param string $event Event name.
         * @param string $providerClass Provider class name.
         * @param ProviderInterface $provider Provider instance.
         * @param Throwable|null $error Optional error for failed event.
         */
        protected function dispatch (string $event, string $providerClass, ProviderInterface $provider, ?Throwable $error = null) : void {
            foreach ($this->listeners[$event] ?? [] as $listener) {
                if ($error !== null) {
                    $listener($providerClass, $provider, $error);
                }
                else $listener($providerClass, $provider);
            }
        }

        /**
         * Resolves deterministic provider order using dependencies, then priority, then class name.
         * @throws RuntimeException If dependencies are missing or cyclic.
         * @return array<int, class-string<ProviderInterface>> Ordered provider class names.
         */
        protected function getOrderedProviderClasses () : array {
            $providerClasses = array_keys($this->providers);
            $graph = [];
            $inDegree = [];

            foreach ($providerClasses as $providerClass) {
                $graph[$providerClass] = [];
                $inDegree[$providerClass] = 0;
            }

            foreach ($providerClasses as $providerClass) {
                $provider = $this->providers[$providerClass];

                foreach ($provider->getDependencies() as $dependencyClass) {
                    if (!isset($this->providers[$dependencyClass])) {
                        throw new RuntimeException("Provider '$providerClass' depends on '$dependencyClass', but that provider is not registered.");
                    }

                    $graph[$dependencyClass][] = $providerClass;
                    $inDegree[$providerClass]++;
                }

                foreach ($provider->getSoftDependencies() as $dependencyClass) {
                    if (!isset($this->providers[$dependencyClass])) {
                        continue;
                    }

                    $graph[$dependencyClass][] = $providerClass;
                    $inDegree[$providerClass]++;
                }

                foreach ($this->externalDependencies[$providerClass] ?? [] as $dependencyClass) {
                    if (!isset($this->providers[$dependencyClass])) {
                        continue;
                    }

                    $graph[$dependencyClass][] = $providerClass;
                    $inDegree[$providerClass]++;
                }
            }

            $queue = [];
            foreach ($providerClasses as $providerClass) {
                if ($inDegree[$providerClass] === 0) {
                    $queue[] = $providerClass;
                }
            }

            $this->sortQueue($queue);

            $ordered = [];

            while (!empty($queue)) {
                $providerClass = array_shift($queue);
                $ordered[] = $providerClass;

                foreach ($graph[$providerClass] as $next) {
                    $inDegree[$next]--;

                    if ($inDegree[$next] === 0) {
                        $queue[] = $next;
                    }
                }

                $this->sortQueue($queue);
            }

            if (count($ordered) !== count($providerClasses)) {
                throw new RuntimeException("Circular provider dependency detected while ordering providers.");
            }

            return $ordered;
        }

        /**
         * Indexes provided abstracts for a deferred provider.
         * @param class-string<ProviderInterface> $providerClass Provider class name.
         * @param ProviderInterface $provider Provider instance.
         * @throws RuntimeException If duplicate deferred abstract mappings are detected.
         */
        protected function indexDeferredProvider (string $providerClass, ProviderInterface $provider) : void {
            foreach ($provider->getProvidedAbstracts() as $abstract) {
                $existing = $this->deferredByAbstract[$abstract] ?? null;

                if ($existing !== null && $existing !== $providerClass) {
                    throw new RuntimeException("Deferred abstract '$abstract' is claimed by both '$existing' and '$providerClass'.");
                }

                $this->deferredByAbstract[$abstract] = $providerClass;
            }
        }

        /**
         * Instantiates a provider class.
         * @param class-string<ProviderInterface> $providerClass Provider class name.
         * @throws RuntimeException If class is missing or does not implement ProviderInterface.
         * @return ProviderInterface Provider instance.
         */
        protected function instantiateProvider (string $providerClass) : ProviderInterface {
            if (!class_exists($providerClass)) {
                throw new RuntimeException("Provider class '$providerClass' does not exist.");
            }

            $provider = new $providerClass($this->container);

            if (!$provider instanceof ProviderInterface) {
                throw new RuntimeException("Provider class '$providerClass' must implement ProviderInterface.");
            }

            return $provider;
        }

        /**
         * Registers a provider once.
         * @param class-string<ProviderInterface> $providerClass Provider class name.
         * @throws RuntimeException If registration fails.
         */
        protected function registerProvider (string $providerClass) : void {
            if (isset($this->registered[$providerClass])) {
                return;
            }

            $provider = $this->providers[$providerClass];
            $this->dispatch("registering", $providerClass, $provider);

            try {
                $provider->register();
            }
            catch (Throwable $error) {
                $this->dispatch("failed", $providerClass, $provider, $error);
                throw new RuntimeException("Provider '$providerClass' failed during register().", 0, $error);
            }

            $this->registered[$providerClass] = true;
            $this->dispatch("registered", $providerClass, $provider);
        }

        /**
         * Removes all deferred abstract mappings belonging to a provider.
         * @param class-string<ProviderInterface> $providerClass Provider class name.
         */
        protected function removeDeferredMappingsFor (string $providerClass) : void {
            foreach ($this->deferredByAbstract as $abstract => $ownerClass) {
                if ($ownerClass === $providerClass) {
                    unset($this->deferredByAbstract[$abstract]);
                }
            }
        }

        /**
         * Sorts zero-dependency queue by priority descending, then class name ascending.
         * @param array<int, class-string<ProviderInterface>> $queue Queue to sort in-place.
         */
        protected function sortQueue (array &$queue) : void {
            usort($queue, function (string $leftClass, string $rightClass) : int {
                $left = $this->providers[$leftClass];
                $right = $this->providers[$rightClass];

                $priorityComparison = $right->getPriority() <=> $left->getPriority();
                if ($priorityComparison !== 0) {
                    return $priorityComparison;
                }

                return $leftClass <=> $rightClass;
            });
        }

        /**
         * Declares an external ordering constraint between two registered providers.
         *
         * Both $dependent and $dependency are resolved at boot time against the registered
         * provider list. If either is absent, the constraint is silently discarded, making
         * this safe for optional cross-package wiring.
         *
         * @param class-string<ProviderInterface> $dependent Provider that must run after $dependency.
         * @param class-string<ProviderInterface> $dependency Provider that must run first.
         * @return static
         */
        public function addDependency (string $dependent, string $dependency) : static {
            $this->externalDependencies[$dependent][] = $dependency;
            return $this;
        }

        /**
         * Adds a provider instance or provider class.
         * @param ProviderInterface|class-string<ProviderInterface> $provider Provider instance or class name.
         * @throws RuntimeException If a provider class cannot be instantiated.
         * @return static
         */
        public function addProvider (ProviderInterface|string $provider) : static {
            $instance = is_string($provider)
                ? $this->instantiateProvider($provider)
                : $provider;

            $this->providers[$instance::class] = $instance;
            return $this;
        }

        /**
         * Adds multiple providers.
         * @param array<int, ProviderInterface|class-string<ProviderInterface>> $providers Providers to add.
         * @throws RuntimeException If a provider class cannot be instantiated.
         * @return static
         */
        public function addProviders (array $providers) : static {
            foreach ($providers as $provider) {
                $this->addProvider($provider);
            }

            return $this;
        }

        /**
         * Boots provider lifecycle for all currently added providers.
         *
         * Steps:
         * 1. Resolve provider order.
         * 2. Register all non-deferred providers.
         * 3. Boot all non-deferred providers.
         * 4. Index deferred providers by provided abstract identifier.
         *
         * @throws RuntimeException If provider dependencies are invalid or duplicate deferred keys exist.
         * @return static
         */
        public function boot () : static {
            $ordered = $this->getOrderedProviderClasses();

            foreach ($ordered as $providerClass) {
                $provider = $this->providers[$providerClass];

                if ($provider->isDeferred()) {
                    $this->indexDeferredProvider($providerClass, $provider);
                    continue;
                }

                $this->registerProvider($providerClass);
            }

            foreach ($ordered as $providerClass) {
                $provider = $this->providers[$providerClass];

                if ($provider->isDeferred()) continue;

                $this->bootProvider($providerClass);
            }

            return $this;
        }

        /**
         * Registers a listener that fires after a provider is booted.
         * @param callable $listener Listener signature: (string $providerClass, ProviderInterface $provider) => void
         * @return static
         */
        public function onBooted (callable $listener) : static {
            $this->listeners["booted"][] = $listener;
            return $this;
        }

        /**
         * Registers a listener that fires before a provider is booted.
         * @param callable $listener Listener signature: (string $providerClass, ProviderInterface $provider) => void
         * @return static
         */
        public function onBooting (callable $listener) : static {
            $this->listeners["booting"][] = $listener;
            return $this;
        }

        /**
         * Registers a listener that fires when provider registration/boot fails.
         * @param callable $listener Listener signature: (string $providerClass, ProviderInterface $provider, Throwable $error) => void
         * @return static
         */
        public function onFailed (callable $listener) : static {
            $this->listeners["failed"][] = $listener;
            return $this;
        }

        /**
         * Registers a listener that fires after a provider is registered.
         * @param callable $listener Listener signature: (string $providerClass, ProviderInterface $provider) => void
         * @return static
         */
        public function onRegistered (callable $listener) : static {
            $this->listeners["registered"][] = $listener;
            return $this;
        }

        /**
         * Registers a listener that fires before a provider is registered.
         * @param callable $listener Listener signature: (string $providerClass, ProviderInterface $provider) => void
         * @return static
         */
        public function onRegistering (callable $listener) : static {
            $this->listeners["registering"][] = $listener;
            return $this;
        }

        /**
         * Resolves and boots deferred provider(s) for the given abstract.
         * @param string $abstract Abstract identifier being resolved.
         * @throws RuntimeException If deferred provider registration/boot fails.
         * @return static
         */
        public function resolveDeferred (string $abstract) : static {
            $providerClass = $this->deferredByAbstract[$abstract] ?? null;

            if ($providerClass === null) {
                return $this;
            }

            $this->registerProvider($providerClass);
            $this->bootProvider($providerClass);
            $this->removeDeferredMappingsFor($providerClass);
            return $this;
        }
    }
?>