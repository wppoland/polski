<?php

declare(strict_types=1);

namespace Spolszczony\Repository;

use wpdb;

final class ConsentLogRepository
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'spolszczony_consent_log';
    }

    // Additional methods will be implemented in Phase 3.
}
