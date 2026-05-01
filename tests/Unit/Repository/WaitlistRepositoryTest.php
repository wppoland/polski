<?php

declare(strict_types=1);

namespace {
    if (! function_exists('current_time')) {
        function current_time(string $type, bool $gmt = false): string
        {
            unset($type, $gmt);
            return '2026-04-23 10:00:00';
        }
    }

    if (! class_exists('wpdb')) {
        class wpdb
        {
            public string $prefix = 'wp_';

            public int $insert_id = 0;

            /** @var array<int, array<string, mixed>> */
            public array $rows = [];

            public function prepare(string $query, mixed ...$args): array
            {
                return [$query, $args];
            }

            /**
             * @param array{0:string,1:array<int,mixed>} $prepared
             */
            public function get_row(array $prepared): ?\stdClass
            {
                [, $args] = $prepared;
                $productId = (int) ($args[1] ?? 0);
                $email = (string) ($args[2] ?? '');

                foreach ($this->rows as $row) {
                    if ((int) $row['product_id'] === $productId && (string) $row['email'] === $email) {
                        return (object) $row;
                    }
                }

                return null;
            }

            /**
             * @param array{0:string,1:array<int,mixed>} $prepared
             * @return list<object>
             */
            public function get_results(array $prepared): array
            {
                [, $args] = $prepared;
                $productId = (int) ($args[1] ?? 0);
                $results = [];

                foreach ($this->rows as $row) {
                    if ((int) $row['product_id'] === $productId && (int) $row['notified'] === 0) {
                        $results[] = (object) $row;
                    }
                }

                return $results;
            }

            /**
             * @param array<string, mixed> $data
             * @param list<string> $format
             */
            public function insert(string $table, array $data, array $format): bool
            {
                unset($table, $format);

                $this->insert_id++;
                $data['id'] = $this->insert_id;
                $this->rows[] = $data;

                return true;
            }

            /**
             * @param array<string, mixed> $data
             * @param array<string, mixed> $where
             * @param list<string> $format
             * @param list<string> $whereFormat
             */
            public function update(string $table, array $data, array $where, array $format = [], array $whereFormat = []): bool
            {
                unset($table, $format, $whereFormat);

                foreach ($this->rows as &$row) {
                    if ((int) $row['id'] === (int) ($where['id'] ?? 0)) {
                        $row = array_merge($row, $data);
                        return true;
                    }
                }

                return false;
            }
        }
    }
}

namespace Polski\Tests\Unit\Repository {

    use PHPUnit\Framework\TestCase;
    use Polski\Repository\WaitlistRepository;

    final class WaitlistRepositoryTest extends TestCase
    {
        public function testSubscribeResetsPreviouslyNotifiedEntry(): void
        {
            $wpdb = new \wpdb();
            $wpdb->rows[] = [
                'id' => 7,
                'product_id' => 123,
                'email' => 'buyer@example.com',
                'user_id' => 4,
                'notified' => 1,
                'created_at' => '2026-04-01 10:00:00',
                'notified_at' => '2026-04-02 10:00:00',
            ];

            $repository = new WaitlistRepository($wpdb);

            $id = $repository->subscribe(123, 'buyer@example.com', 9);

            self::assertSame(7, $id);
            self::assertCount(1, $wpdb->rows);
            self::assertSame(0, $wpdb->rows[0]['notified']);
            self::assertNull($wpdb->rows[0]['notified_at']);
            self::assertSame(9, $wpdb->rows[0]['user_id']);
            self::assertSame('2026-04-23 10:00:00', $wpdb->rows[0]['created_at']);
        }

        public function testSubscribeCreatesNewRowWhenEmailIsNew(): void
        {
            $wpdb = new \wpdb();
            $repository = new WaitlistRepository($wpdb);

            $id = $repository->subscribe(55, 'new@example.com', null);

            self::assertSame(1, $id);
            self::assertCount(1, $wpdb->rows);
            self::assertSame(55, $wpdb->rows[0]['product_id']);
            self::assertSame('new@example.com', $wpdb->rows[0]['email']);
            self::assertSame(0, $wpdb->rows[0]['notified']);
        }
    }
}
