<?php

declare(strict_types=1);

namespace Spolszczony\Hook;

use Spolszczony\Contract\HasHooks;
use Spolszczony\Service\EmailService;

final class EmailHooks implements HasHooks
{
    public function __construct(
        private readonly EmailService $emailService,
    ) {
    }

    public function registerHooks(): void
    {
        // Email hooks will be implemented in Phase 6.
    }
}
