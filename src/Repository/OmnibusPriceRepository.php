<?php

declare(strict_types=1);

namespace Spolszczony\Repository;

use wpdb;

final class OmnibusPriceRepository
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'spolszczony_price_history';
    }

    public function deleteOlderThan(int $days): int
    {
        $table = $this->tableName();
        $date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        return (int) $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$table} WHERE recorded_at < %s",
                $date,
            ),
        );
    }

    // Additional methods will be implemented in Phase 2.
}
