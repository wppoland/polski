<?php

declare(strict_types=1);

namespace Polski\Contract;

/**
 * Database migration runnable by {@see \Polski\Migrator}.
 *
 * Each migration class must define a public VERSION constant and implement run().
 */
interface Migration
{
    public function run(): void;
}
