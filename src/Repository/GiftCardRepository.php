<?php

declare(strict_types=1);

namespace Polski\Repository;

use Polski\Model\GiftCard;
use wpdb;

/**
 * Persistence for gift cards and their ledger.
 */
final class GiftCardRepository
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'polski_gift_cards';
    }

    public function transactionTableName(): string
    {
        return $this->wpdb->prefix . 'polski_gift_card_transactions';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $this->wpdb->insert(
            $this->tableName(),
            [
                'code' => (string) $data['code'],
                'initial_balance' => (float) $data['initial_balance'],
                'balance' => (float) $data['balance'],
                'currency' => (string) $data['currency'],
                'purchaser_user_id' => $data['purchaser_user_id'] ?: null,
                'purchaser_email' => (string) $data['purchaser_email'],
                'recipient_name' => (string) $data['recipient_name'],
                'recipient_email' => (string) $data['recipient_email'],
                'sender_name' => (string) $data['sender_name'],
                'message' => (string) $data['message'],
                'order_id' => (int) $data['order_id'],
                'product_id' => (int) $data['product_id'],
                'status' => (string) ($data['status'] ?? 'active'),
                'expires_at' => $data['expires_at'] ?? null,
                'created_at' => current_time('mysql', true),
            ],
            ['%s', '%f', '%f', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s'],
        );

        $id = (int) $this->wpdb->insert_id;

        if ($id > 0) {
            $this->addTransaction($id, (int) $data['order_id'], 'credit', (float) $data['balance'], 'Gift card created');
        }

        return $id;
    }

    public function findByCode(string $code): ?GiftCard
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM ' . $this->tableName() . ' WHERE code = %s LIMIT 1', $code),
        );

        return $row !== null ? GiftCard::fromRow($row) : null;
    }

    /**
     * @return list<GiftCard>
     */
    public function findByOrder(int $orderId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare('SELECT * FROM ' . $this->tableName() . ' WHERE order_id = %d ORDER BY created_at DESC', $orderId),
        );

        return array_map(
            static fn (object $row): GiftCard => GiftCard::fromRow($row),
            is_array($rows) ? $rows : [],
        );
    }

    /**
     * @return list<GiftCard>
     */
    public function findForAccount(int $userId, string $email): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM ' . $this->tableName() . ' WHERE purchaser_user_id = %d OR purchaser_email = %s OR recipient_email = %s ORDER BY created_at DESC',
                $userId,
                $email,
                $email,
            ),
        );

        return array_map(
            static fn (object $row): GiftCard => GiftCard::fromRow($row),
            is_array($rows) ? $rows : [],
        );
    }

    public function debit(int $giftCardId, int $orderId, float $amount, string $note = 'Gift card redeemed'): void
    {
        $card = $this->findById($giftCardId);

        if ($card === null) {
            return;
        }

        $balance = max(0.0, $card->balance - $amount);
        $status = $balance > 0 ? $card->status : 'redeemed';

        $this->wpdb->update(
            $this->tableName(),
            [
                'balance' => $balance,
                'status' => $status,
            ],
            ['id' => $giftCardId],
            ['%f', '%s'],
            ['%d'],
        );

        $this->addTransaction($giftCardId, $orderId, 'debit', $amount, $note);
    }

    public function findById(int $id): ?GiftCard
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM ' . $this->tableName() . ' WHERE id = %d LIMIT 1', $id),
        );

        return $row !== null ? GiftCard::fromRow($row) : null;
    }

    public function existsForOrderAndCode(int $orderId, string $code): bool
    {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $this->tableName() . ' WHERE order_id = %d AND code = %s',
                $orderId,
                $code,
            ),
        );

        return (int) $count > 0;
    }

    private function addTransaction(int $giftCardId, int $orderId, string $type, float $amount, string $note): void
    {
        $this->wpdb->insert(
            $this->transactionTableName(),
            [
                'gift_card_id' => $giftCardId,
                'order_id' => $orderId > 0 ? $orderId : null,
                'type' => $type,
                'amount' => $amount,
                'note' => $note,
                'created_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%s', '%f', '%s', '%s'],
        );
    }
}
