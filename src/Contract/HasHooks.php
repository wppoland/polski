<?php

declare(strict_types=1);

namespace Polski\Contract;

/**
 * A service that registers WordPress hooks (actions and filters).
 */
interface HasHooks
{
    public function registerHooks(): void;
}
