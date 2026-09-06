<?php

declare(strict_types=1);

namespace Polski\Migration;

use Polski\Contract\Migration;
use Polski\Service\WithdrawalOrderStatusService;

defined('ABSPATH') || exit;

/**
 * Repairs orders left with an unusable withdrawal status.
 *
 * The three withdrawal statuses used to be `wc-withdrawal-requested`,
 * `wc-withdrawal-partial` and `wc-withdrawal-completed`, 23, 21 and 23
 * characters. An order status is stored in a `varchar(20)` column, so every
 * one of them was cut short on write and the order ended up carrying a status
 * that is not registered anywhere: it vanished from WooCommerce - Orders while
 * still opening from its own URL.
 *
 * The slugs are now short enough to store. This rewrites the values already in
 * the database, both the truncated ones that were actually written and the full
 * ones, in case a store widened the column by hand.
 */
final class Migration_2_9_0 implements Migration
{
    public const VERSION = '2.9.0';

    public function run(): void
    {
        global $wpdb;

        foreach ($this->replacements() as $old => $new) {
            // HPOS.
            $ordersTable = $wpdb->prefix . 'wc_orders';
            if ($this->tableExists($ordersTable)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off repair of a WooCommerce table, no caching layer applies.
                $wpdb->update($ordersTable, ['status' => $new], ['status' => $old], ['%s'], ['%s']);
            }

            // Legacy post storage (also the HPOS backfill table when sync is on).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off repair of core table, no caching layer applies.
            $wpdb->update($wpdb->posts, ['post_status' => $new], ['post_status' => $old, 'post_type' => 'shop_order'], ['%s'], ['%s', '%s']);

            // Analytics keeps its own copy so reports stay consistent.
            $statsTable = $wpdb->prefix . 'wc_order_stats';
            if ($this->tableExists($statsTable)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off repair of a WooCommerce table, no caching layer applies.
                $wpdb->update($statsTable, ['status' => $new], ['status' => $old], ['%s'], ['%s']);
            }
        }

        // Order objects are cached by id; a direct UPDATE leaves stale copies.
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('orders');
        }
    }

    /**
     * Old stored value (truncated and full) mapped to the current slug.
     *
     * @return array<string, string>
     */
    private function replacements(): array
    {
        $map = [];

        foreach ([
            'wc-withdrawal-requested' => WithdrawalOrderStatusService::STATUS_REQUESTED,
            'wc-withdrawal-partial' => WithdrawalOrderStatusService::STATUS_PARTIAL,
            'wc-withdrawal-completed' => WithdrawalOrderStatusService::STATUS_COMPLETED,
        ] as $full => $new) {
            $map[$full] = $new;
            $map[substr($full, 0, WithdrawalOrderStatusService::MAX_STATUS_LENGTH)] = $new;
        }

        return $map;
    }

    private function tableExists(string $table): bool
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe, prepared.
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}
