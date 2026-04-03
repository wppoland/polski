<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Polski\Model\ProductListItem;
use Polski\Model\WishlistItem;
use Polski\Model\CompareItem;

final class ProductListItemTest extends TestCase
{
    public function testFromRowCreatesInstance(): void
    {
        $row = (object) [
            'id' => '10',
            'product_id' => '55',
            'user_id' => '3',
            'session_id' => null,
            'created_at' => '2026-04-01 10:00:00',
        ];

        $item = ProductListItem::fromRow($row);

        $this->assertSame(10, $item->id);
        $this->assertSame(55, $item->productId);
        $this->assertSame(3, $item->userId);
        $this->assertNull($item->sessionId);
    }

    public function testWishlistItemExtendsProductListItem(): void
    {
        $item = new WishlistItem(
            id: 1,
            productId: 100,
            userId: 5,
            sessionId: null,
            createdAt: new \DateTimeImmutable(),
        );

        $this->assertInstanceOf(ProductListItem::class, $item);
    }

    public function testCompareItemExtendsProductListItem(): void
    {
        $item = new CompareItem(
            id: 2,
            productId: 200,
            userId: null,
            sessionId: 'guest_xyz',
            createdAt: new \DateTimeImmutable(),
        );

        $this->assertInstanceOf(ProductListItem::class, $item);
        $this->assertSame('guest_xyz', $item->sessionId);
    }
}
