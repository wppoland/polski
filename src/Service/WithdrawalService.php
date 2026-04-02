<?php

declare(strict_types=1);

namespace Spolszczony\Service;

use Spolszczony\Repository\WithdrawalRepository;

final class WithdrawalService
{
    public function __construct(
        private readonly WithdrawalRepository $repository,
        private readonly EmailService $emailService,
    ) {
    }

    // Placeholder — will be fully implemented in Phase 4.
}
