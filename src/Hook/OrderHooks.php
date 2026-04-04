<?php

declare(strict_types=1);
namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

final class OrderHooks implements HasHooks
{
    public function registerHooks(): void
    {
        // Order hooks will be implemented in Phase 3.
    }
}
