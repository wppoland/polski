<?php

declare(strict_types=1);
namespace Polski\Repository;

defined('ABSPATH') || exit;
/**
 * Data access for wishlist items.
 *
 * Inherits add, remove, exists, findAll, count, clear, transferSessionToUser
 * from AbstractProductListRepository. Wishlist sorts newest-first.
 */
final class WishlistRepository extends AbstractProductListRepository
{
    protected function tableSuffix(): string
    {
        return 'wishlist_items';
    }

    protected function defaultOrder(): string
    {
        return 'DESC';
    }
}
