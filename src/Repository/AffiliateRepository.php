<?php

declare(strict_types=1);

namespace Polski\Repository;

use Polski\Model\Affiliate;
use Polski\Model\AffiliateReferral;
use wpdb;

/**
 * Persistence for affiliates and referrals.
 */
final class AffiliateRepository
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function affiliatesTable(): string
    {
        return $this->wpdb->prefix . 'polski_affiliates';
    }

    public function referralsTable(): string
    {
        return $this->wpdb->prefix . 'polski_affiliate_referrals';
    }

    public function findAffiliateByUserId(int $userId): ?Affiliate
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM ' . $this->affiliatesTable() . ' WHERE user_id = %d LIMIT 1', $userId),
        );

        return $row !== null ? Affiliate::fromRow($row) : null;
    }

    public function findAffiliateByToken(string $token): ?Affiliate
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM ' . $this->affiliatesTable() . ' WHERE token = %s LIMIT 1', $token),
        );

        return $row !== null ? Affiliate::fromRow($row) : null;
    }

    public function createAffiliate(int $userId, string $token): int
    {
        $this->wpdb->insert(
            $this->affiliatesTable(),
            [
                'user_id' => $userId,
                'token' => $token,
                'status' => 'active',
                'created_at' => current_time('mysql', true),
            ],
            ['%d', '%s', '%s', '%s'],
        );

        return (int) $this->wpdb->insert_id;
    }

    public function referralExists(int $affiliateId, int $orderId): bool
    {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $this->referralsTable() . ' WHERE affiliate_id = %d AND order_id = %d',
                $affiliateId,
                $orderId,
            ),
        );

        return (int) $count > 0;
    }

    public function createReferral(int $affiliateId, int $orderId, string $customerEmail, float $orderTotal, float $commissionAmount, string $status): int
    {
        $this->wpdb->insert(
            $this->referralsTable(),
            [
                'affiliate_id' => $affiliateId,
                'order_id' => $orderId,
                'customer_email' => $customerEmail,
                'order_total' => $orderTotal,
                'commission_amount' => $commissionAmount,
                'status' => $status,
                'created_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%f', '%f', '%s', '%s'],
        );

        return (int) $this->wpdb->insert_id;
    }

    /**
     * @return list<AffiliateReferral>
     */
    public function findReferralsByAffiliate(int $affiliateId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM ' . $this->referralsTable() . ' WHERE affiliate_id = %d ORDER BY created_at DESC',
                $affiliateId,
            ),
        );

        return array_map(
            static fn (object $row): AffiliateReferral => AffiliateReferral::fromRow($row),
            is_array($rows) ? $rows : [],
        );
    }

    /**
     * @return array{referrals:int, revenue:float, commission:float}
     */
    public function getStats(int $affiliateId): array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                'SELECT COUNT(*) AS referrals, COALESCE(SUM(order_total),0) AS revenue, COALESCE(SUM(commission_amount),0) AS commission FROM ' . $this->referralsTable() . ' WHERE affiliate_id = %d',
                $affiliateId,
            ),
        );

        return [
            'referrals' => (int) ($row->referrals ?? 0),
            'revenue' => (float) ($row->revenue ?? 0),
            'commission' => (float) ($row->commission ?? 0),
        ];
    }
}
