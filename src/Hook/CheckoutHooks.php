<?php

declare(strict_types=1);

namespace Spolszczony\Hook;

use Spolszczony\Contract\HasHooks;
use Spolszczony\Service\CheckboxService;
use Spolszczony\Repository\ConsentLogRepository;

final class CheckoutHooks implements HasHooks
{
    public function __construct(
        private readonly CheckboxService $checkboxes,
        private readonly ConsentLogRepository $consentLog,
    ) {
    }

    public function registerHooks(): void
    {
        // Checkout hooks will be implemented in Phase 3.
    }
}
