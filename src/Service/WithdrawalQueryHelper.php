<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Enum\WithdrawalStatus;
use Polski\Repository\WithdrawalRepository;

/**
 * Adds `wc_get_orders()` query helpers for the withdrawal flow so other
 * plugins and themes can ask "which orders currently have a withdrawal
 * request?" without joining the `polski_withdrawals` table directly.
 *
 * Two new parameters are exposed:
 *
 *  - `polski_has_withdrawal` (bool)        - filter to orders that do (or do
 *                                            not) have any non-rejected
 *                                            withdrawal record.
 *  - `polski_withdrawal_status` (string)   - exact match on the withdrawal
 *                                            status: requested / confirmed /
 *                                            completed / rejected.
 *
 * Both work on HPOS and legacy storage because WC delegates filtering to
 * the configured data store; we hook into `woocommerce_order_query` (the
 * post-fetch filter) so the work is the same on either schema. For large
 * shops this is fine for date-bounded queries (today's withdrawals,
 * last 7 days, etc.) but should not be used to scan the entire order
 * history.
 *
 * Example usage:
 *
 *     $orders = wc_get_orders([
 *         'date_created' => '>' . (time() - 7 * DAY_IN_SECONDS),
 *         'polski_withdrawal_status' => 'requested',
 *         'return' => 'ids',
 *     ]);
 */
final class WithdrawalQueryHelper implements HasHooks
{
    public function __construct(
        private readonly WithdrawalRepository $repository,
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('woocommerce_order_query', [$this, 'filterByWithdrawal'], 10, 2);
    }

    /**
     * Filters the `wc_get_orders()` results when one of our custom query args is
     * present. The `woocommerce_order_query` filter passes EITHER a plain list of
     * orders/ids OR - when the query is run with `paginate => true` - a stdClass
     * of the shape `{ orders, total, max_num_pages }`. We must accept both (and
     * leave anything else untouched), so the parameter is intentionally untyped:
     * a strict `array` hint here fatals on every paginated order query, e.g. the
     * WooCommerce admin Orders screen.
     *
     * @param list<\WC_Order|int>|object|mixed $results
     * @param array<string, mixed>|mixed       $args
     *
     * @return mixed The same shape that was passed in.
     */
    public function filterByWithdrawal($results, $args)
    {
        if (! is_array($args)) {
            return $results;
        }

        $hasFlag = $args['polski_has_withdrawal'] ?? null;
        $statusArg = isset($args['polski_withdrawal_status'])
            ? (string) $args['polski_withdrawal_status']
            : null;

        if ($hasFlag === null && $statusArg === null) {
            return $results;
        }

        $status = $statusArg !== null ? WithdrawalStatus::tryFrom($statusArg) : null;
        $matchingOrderIds = $this->matchingOrderIds($status);

        // Negative filter drops matching orders; positive keeps only matches.
        $keep = static fn ($order): bool => in_array(self::orderId($order), $matchingOrderIds, true);
        $predicate = $hasFlag === false
            ? static fn ($order): bool => ! $keep($order)
            : $keep;

        // Paginated result: filter the inner list, keep the object shape, and
        // best-effort adjust the total by the number removed from this page.
        if (is_object($results) && isset($results->orders) && is_array($results->orders)) {
            $filtered = array_values(array_filter($results->orders, $predicate));
            $removed = count($results->orders) - count($filtered);
            $results->orders = $filtered;
            if (isset($results->total) && is_numeric($results->total)) {
                $results->total = max(0, (int) $results->total - $removed);
            }

            return $results;
        }

        if (is_array($results)) {
            return array_values(array_filter($results, $predicate));
        }

        // Unknown shape (should not happen) - never fatal, return untouched.
        return $results;
    }

    /**
     * @return list<int>
     */
    private function matchingOrderIds(?WithdrawalStatus $status): array
    {
        // Pull up to 5_000 records - enough for any reasonable filtered query.
        // Storefronts needing more should narrow with date_created upstream.
        $rows = $this->repository->findAll(5000, 0, $status);

        $ids = [];
        foreach ($rows as $row) {
            if ($row->status === WithdrawalStatus::Rejected && $status === null) {
                // "polski_has_withdrawal => true" should skip rejected records
                // unless the caller explicitly asked for the rejected status.
                continue;
            }
            $ids[] = $row->orderId;
        }

        return array_values(array_unique($ids));
    }

    private static function orderId(\WC_Order|int $order): int
    {
        return $order instanceof \WC_Order ? $order->get_id() : (int) $order;
    }
}
