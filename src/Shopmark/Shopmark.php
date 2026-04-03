<?php

declare(strict_types=1);

namespace Polski\Shopmark;

/**
 * A display element definition (e.g., unit price badge, delivery time notice).
 */
final class Shopmark
{
    public function __construct(
        public readonly string $id,
        public readonly Location $location,
        public readonly string $hookName,
        public readonly int $priority,
        public readonly \Closure $callback,
        public readonly bool $enabled = true,
    ) {
    }
}
