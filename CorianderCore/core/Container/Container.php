<?php
declare(strict_types=1);

/*
 * Container orchestrates service creation and retrieval.
 *
 * Workflow:
 * 1. Register factories or instances via set().
 * 2. Services are lazily instantiated when get() is invoked.
 * 3. Instantiated services are cached for subsequent calls.
 */

namespace CorianderCore\Core\Container;

use InvalidArgumentException;

/**
 * Lightweight service container for managing application dependencies.
 */
class Container
{
    /**
     * @var array<string, callable|object> Definitions for services.
     */
    private array $definitions = [];

    /**
     * @var array<string, object> Cached service instances.
     */
    private array $services = [];

    /**
     * Register a service factory or instance.
     *
     * @param string              $id       Service identifier.
     * @param callable|object     $concrete Factory callable or concrete instance.
     *
     * @return void
     */
    public function set(string $id, callable|object $concrete): void
    {
        $this->definitions[$id] = $concrete;
    }

    /**
     * Retrieve a service from the container.
     *
     * @param string $id Service identifier to retrieve.
     *
     * @return object Resolved service instance.
     *
     * @throws InvalidArgumentException If the service is not defined.
     */
    public function get(string $id): object
    {
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        if (!isset($this->definitions[$id])) {
            throw new InvalidArgumentException("Service '{$id}' is not defined.");
        }

        $concrete = $this->definitions[$id];
        $service = is_callable($concrete) ? $concrete($this) : $concrete;

        return $this->services[$id] = $service;
    }
}
