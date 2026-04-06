<?php

declare(strict_types=1);
namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

final class CartHooks implements HasHooks
{
    public function registerHooks(): void
    {
        // Cart hooks will be implemented in Phase 2.
    }
}
