<?php

declare(strict_types=1);

namespace Spolszczony\Repository;

use wpdb;

final class WithdrawalRepository
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'spolszczony_withdrawals';
    }

    // Additional methods will be implemented in Phase 4.
}
