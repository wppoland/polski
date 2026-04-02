<?php

declare(strict_types=1);

namespace Spolszczony;

use InvalidArgumentException;

/**
 * Lightweight dependency injection container.
 *
 * Supports singleton and factory bindings. No external dependencies.
 */
final class Container
{
    /** @var array<class-string, callable> */
    private array $factories = [];

    /** @var array<class-string, object> */
    private array $singletons = [];

    /** @var array<class-string, true> */
    private array $shared = [];

    /**
     * Register a shared (singleton) service.
     *
     * @template T of object
     * @param class-string<T> $id
     * @param callable(): T $factory
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = true;
    }

    /**
     * Register a transient (new instance each time) service.
     *
     * @template T of object
     * @param class-string<T> $id
     * @param callable(): T $factory
     */
    public function bind(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->shared[$id]);
    }

    /**
     * Resolve a service from the container.
     *
     * @template T of object
     * @param class-string<T> $id
     * @return T
     * @throws InvalidArgumentException If the service is not registered.
     */
    public function get(string $id): object
    {
        if (isset($this->singletons[$id])) {
            /** @var T */
            return $this->singletons[$id];
        }

        if (! isset($this->factories[$id])) {
            throw new InvalidArgumentException(
                sprintf('Service "%s" is not registered in the container.', $id),
            );
        }

        $instance = ($this->factories[$id])();

        if (isset($this->shared[$id])) {
            $this->singletons[$id] = $instance;
        }

        /** @var T */
        return $instance;
    }

    /**
     * Check if a service is registered.
     *
     * @param class-string $id
     */
    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->singletons[$id]);
    }

    /**
     * Store an already-instantiated object as a singleton.
     *
     * @template T of object
     * @param class-string<T> $id
     * @param T $instance
     */
    public function instance(string $id, object $instance): void
    {
        $this->singletons[$id] = $instance;
        $this->shared[$id] = true;
    }
}
