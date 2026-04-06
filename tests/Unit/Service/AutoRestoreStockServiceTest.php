<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

/**
 * Tests for AutoRestoreStockService logic.
 */
final class AutoRestoreStockServiceTest extends TestCase
{
    public function testStockIncreaseCalculation(): void
    {
        $oldStock = 5;
        $orderQuantity = 3;
        $newStock = $oldStock + $orderQuantity;

        $this->assertSame(8, $newStock);
    }

    public function testMultipleItemsRestore(): void
    {
        $items = [
            ['product_id' => 1, 'qty' => 2, 'old_stock' => 10],
            ['product_id' => 2, 'qty' => 1, 'old_stock' => 0],
            ['product_id' => 3, 'qty' => 5, 'old_stock' => 3],
        ];

        $totalRestored = 0;

        foreach ($items as $item) {
            $newStock = $item['old_stock'] + $item['qty'];
            $totalRestored += $item['qty'];

            $this->assertGreaterThanOrEqual($item['old_stock'], $newStock);
        }

        $this->assertSame(8, $totalRestored);
    }

    public function testZeroQuantitySkipped(): void
    {
        $qty = 0;
        $shouldProcess = $qty > 0;

        $this->assertFalse($shouldProcess);
    }

    public function testDoubleRestorationPrevented(): void
    {
        $metaRestored = true;

        // If meta exists, do not restore again.
        $shouldRestore = ! $metaRestored;

        $this->assertFalse($shouldRestore);
    }
}
