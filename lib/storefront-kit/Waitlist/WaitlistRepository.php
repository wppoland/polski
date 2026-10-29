<?php

declare(strict_types=1);

namespace Polski\Vendor\StorefrontKit\Waitlist;

defined('ABSPATH') || exit;

interface WaitlistRepository
{
    public function subscribe(int $productId, string $email, ?int $userId): int;

    /**
     * @return iterable<object{id:int,email:string}>
     */
    public function findPendingByProduct(int $productId): iterable;

    public function markNotified(int $id): void;
}
