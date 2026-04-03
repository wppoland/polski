<?php

declare(strict_types=1);

namespace Polski\Repository;

use Polski\Enum\QuoteRequestStatus;
use Polski\Model\QuoteRequest;
use wpdb;

/**
 * Data access for quote requests.
 */
final class QuoteRequestRepository
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . 'polski_quote_requests';
    }

    /**
     * @param array<string, mixed>|null $meta
     */
    public function create(
        int $productId,
        ?int $variationId,
        ?int $customerId,
        string $customerName,
        string $customerEmail,
        ?string $customerPhone,
        ?string $companyName,
        ?string $nip,
        string $quantity,
        ?string $postcode,
        ?string $message,
        string $source,
        ?string $sourceUrl,
        bool $consented,
        ?array $meta = null,
    ): int {
        $this->wpdb->insert(
            $this->tableName(),
            [
                'product_id' => $productId,
                'variation_id' => $variationId,
                'customer_id' => $customerId,
                'status' => QuoteRequestStatus::New->value,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'company_name' => $companyName,
                'nip' => $nip,
                'quantity' => $quantity,
                'postcode' => $postcode,
                'message' => $message,
                'source' => $source,
                'source_url' => $sourceUrl,
                'consented' => $consented ? 1 : 0,
                'meta_json' => $meta !== null ? wp_json_encode($meta) : null,
                'created_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'],
        );

        return (int) $this->wpdb->insert_id;
    }

    public function findById(int $id): ?QuoteRequest
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                'SELECT * FROM ' . $this->tableName() . ' WHERE id = %d',
                $id,
            ),
        );

        return $row !== null ? QuoteRequest::fromRow($row) : null;
    }

    /**
     * @return list<QuoteRequest>
     */
    public function findAll(int $limit = 100, int $offset = 0, ?QuoteRequestStatus $status = null): array
    {
        $table = $this->tableName();

        if ($status !== null) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $status->value,
                    $limit,
                    $offset,
                ),
            );
        } else {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $limit,
                    $offset,
                ),
            );
        }

        return array_map(
            static fn (object $row): QuoteRequest => QuoteRequest::fromRow($row),
            is_array($rows) ? $rows : [],
        );
    }

    public function updateStatus(int $id, QuoteRequestStatus $status): bool
    {
        $updated = $this->wpdb->update(
            $this->tableName(),
            [
                'status' => $status->value,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d'],
        );

        return $updated !== false;
    }

    public function countByStatus(QuoteRequestStatus $status): int
    {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $this->tableName() . ' WHERE status = %s',
                $status->value,
            ),
        );
    }
}
