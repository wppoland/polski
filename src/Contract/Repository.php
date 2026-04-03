<?php

declare(strict_types=1);

namespace Polski\Contract;

/**
 * Generic repository interface for data access.
 *
 * @template T of object
 */
interface Repository
{
    /**
     * @return T|null
     */
    public function findById(int $id): ?object;

    /**
     * @param T $entity
     */
    public function save(object $entity): int;

    public function delete(int $id): bool;
}
