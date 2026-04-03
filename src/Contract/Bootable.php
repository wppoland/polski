<?php

declare(strict_types=1);

namespace Polski\Contract;

/**
 * A service that requires initialization before use.
 */
interface Bootable
{
    public function boot(): void;
}
