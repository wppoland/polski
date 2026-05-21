<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\ProductInfoService;

/**
 * Pin the per-request memoisation on ProductInfoService::getManufacturer()
 * and ::getBrandTerms(). Both are called multiple times per request when
 * the storefront has structured-data callbacks enabled in addition to the
 * display ones, and the WordPress term cache only de-duplicates the DB
 * hit, not the PHP function-call chain.
 */
final class ProductInfoServiceMemoTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['polski_test_terms'] = [];
    }

    public function testGetManufacturerHitsTheTermBackendOnlyOncePerProduct(): void
    {
        $callCount = 0;

        $GLOBALS['polski_test_terms']['polski_manufacturer'][42] = static function () use (&$callCount): array {
            ++$callCount;
            return [new \WP_Term(7, 'ACME Sp. z o.o.', 'polski_manufacturer', 'acme')];
        };

        $service = new ProductInfoService();
        $product = new FakeProduct(42);

        self::assertSame('ACME Sp. z o.o.', $service->getManufacturer($product));
        self::assertSame('ACME Sp. z o.o.', $service->getManufacturer($product));
        self::assertSame('ACME Sp. z o.o.', $service->getManufacturer($product));

        self::assertSame(1, $callCount, 'getManufacturer must hit get_the_terms only once per product per request');
    }

    public function testGetBrandTermsHitsTheTermBackendOnlyOncePerProduct(): void
    {
        $callCount = 0;

        $GLOBALS['polski_test_terms']['polski_brand'][84] = static function () use (&$callCount): array {
            ++$callCount;
            return [
                new \WP_Term(11, 'Sample Brand', 'polski_brand', 'sample-brand'),
            ];
        };

        $service = new ProductInfoService();
        $product = new FakeProduct(84);

        $first = $service->getBrandTerms($product);
        $second = $service->getBrandTerms($product);

        self::assertCount(1, $first);
        self::assertSame($first, $second);
        self::assertSame(1, $callCount);
    }

    public function testDifferentProductsBypassEachOthersMemo(): void
    {
        $callCount = 0;

        $GLOBALS['polski_test_terms']['polski_manufacturer'][1] = static function () use (&$callCount): array {
            ++$callCount;
            return [new \WP_Term(1, 'M1', 'polski_manufacturer', 'm1')];
        };
        $GLOBALS['polski_test_terms']['polski_manufacturer'][2] = static function () use (&$callCount): array {
            ++$callCount;
            return [new \WP_Term(2, 'M2', 'polski_manufacturer', 'm2')];
        };

        $service = new ProductInfoService();

        $service->getManufacturer(new FakeProduct(1));
        $service->getManufacturer(new FakeProduct(2));
        $service->getManufacturer(new FakeProduct(1));
        $service->getManufacturer(new FakeProduct(2));

        self::assertSame(2, $callCount, 'Each distinct product warms the backend once; subsequent reads are memoised.');
    }
}

/**
 * Minimal stand-in for WC_Product limited to the methods the service uses.
 */
final class FakeProduct extends \WC_Product
{
    public function __construct(private readonly int $productId)
    {
        // Skip parent constructor (it expects a WC bootstrap).
    }

    public function get_id(): int
    {
        return $this->productId;
    }
}
