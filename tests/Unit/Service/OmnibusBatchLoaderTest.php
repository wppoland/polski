<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Enum\PriceType;
use Polski\Model\OmnibusPrice;
use Polski\Repository\OmnibusPriceRepository;
use Polski\Service\OmnibusService;

/**
 * Pin the behaviour of OmnibusService::warmCacheForArchive() so the next
 * refactor cannot accidentally re-introduce the per-product N+1 pattern.
 *
 * The repository is replaced by a recording fake so we can observe what
 * batch fetch was issued; the WP object-cache stubs from tests/bootstrap.php
 * provide a deterministic per-test cache.
 */
final class OmnibusBatchLoaderTest extends TestCase
{
    private FakeOmnibusRepository $repo;

    protected function setUp(): void
    {
        $GLOBALS['polski_test_object_cache'] = [];
        $GLOBALS['polski_test_options'] = [];

        $this->repo = new FakeOmnibusRepository();
    }

    public function testNoopWhenWpQueryIsAbsent(): void
    {
        unset($GLOBALS['wp_query']);

        $service = $this->makeService();
        $service->warmCacheForArchive();

        self::assertSame(0, $this->repo->batchCallCount);
    }

    public function testNoopWhenWpQueryHasNoPosts(): void
    {
        $GLOBALS['wp_query'] = new \WP_Query([]);

        $service = $this->makeService();
        $service->warmCacheForArchive();

        self::assertSame(0, $this->repo->batchCallCount);
    }

    public function testFiltersNonProductPostTypesOut(): void
    {
        $GLOBALS['wp_query'] = new \WP_Query([
            new \WP_Post(101, 'post'),       // not a product
            new \WP_Post(102, 'page'),       // not a product
            new \WP_Post(103, 'product'),    // counted
        ]);

        $this->repo->batchResult = [];

        $service = $this->makeService();
        $service->warmCacheForArchive();

        self::assertSame(1, $this->repo->batchCallCount);
        self::assertSame([103], $this->repo->lastBatchIds);
    }

    public function testDeduplicatesAndPersistsCacheIncludingNulls(): void
    {
        $GLOBALS['wp_query'] = new \WP_Query([
            new \WP_Post(201, 'product'),
            new \WP_Post(202, 'product'),
            new \WP_Post(201, 'product'),   // duplicate, should not be re-fetched
        ]);

        $this->repo->batchResult = [
            201 => new OmnibusPrice(
                id: 1,
                productId: 201,
                price: 49.99,
                salePrice: 39.99,
                priceType: PriceType::Sale,
                currency: 'PLN',
                recordedAt: new \DateTimeImmutable('2026-05-01 12:00:00'),
            ),
            // 202 deliberately absent -> the loader should still cache `null`
        ];

        $service = $this->makeService();
        $service->warmCacheForArchive();

        // De-duplicated input.
        self::assertSame([201, 202], $this->repo->lastBatchIds);

        // Cache is keyed as "<productId>:<days>" in group polski_omnibus.
        $cached201 = wp_cache_get('201:30', 'polski_omnibus');
        $cached202 = wp_cache_get('202:30', 'polski_omnibus');

        self::assertInstanceOf(OmnibusPrice::class, $cached201);
        self::assertSame(39.99, $cached201->effectivePrice());
        self::assertNull($cached202, 'Absent products must be cached as null to prevent re-query');
    }

    public function testSkipsAlreadyCachedProducts(): void
    {
        $GLOBALS['wp_query'] = new \WP_Query([
            new \WP_Post(301, 'product'),
            new \WP_Post(302, 'product'),
            new \WP_Post(303, 'product'),
        ]);

        // Pre-warm 301 + 303.
        wp_cache_set('301:30', null, 'polski_omnibus');
        wp_cache_set('303:30', null, 'polski_omnibus');

        $this->repo->batchResult = [];

        $service = $this->makeService();
        $service->warmCacheForArchive();

        self::assertSame(1, $this->repo->batchCallCount);
        self::assertSame([302], $this->repo->lastBatchIds, 'Only the uncached id should be batched');
    }

    public function testSkipsBatchCallEntirelyWhenEverythingIsCached(): void
    {
        $GLOBALS['wp_query'] = new \WP_Query([
            new \WP_Post(401, 'product'),
            new \WP_Post(402, 'product'),
        ]);

        wp_cache_set('401:30', null, 'polski_omnibus');
        wp_cache_set('402:30', null, 'polski_omnibus');

        $service = $this->makeService();
        $service->warmCacheForArchive();

        self::assertSame(0, $this->repo->batchCallCount);
    }

    private function makeService(): OmnibusService
    {
        $reflection = new \ReflectionClass(OmnibusService::class);
        $service = $reflection->newInstanceWithoutConstructor();

        $repoProp = $reflection->getProperty('repository');
        $repoProp->setValue($service, $this->repo);

        $daysProp = $reflection->getProperty('days');
        $daysProp->setValue($service, 30);

        return $service;
    }
}

/**
 * Recording double for OmnibusPriceRepository. Captures the last batch
 * request so tests can assert on the de-duplicated id list, and serves
 * a pre-configured result map.
 */
final class FakeOmnibusRepository extends OmnibusPriceRepository
{
    public int $batchCallCount = 0;

    /** @var list<int> */
    public array $lastBatchIds = [];

    /** @var array<int, OmnibusPrice> */
    public array $batchResult = [];

    public function __construct()
    {
        // Bypass the wpdb dependency - this fake is only used in unit tests.
    }

    /**
     * @param list<int> $productIds
     * @return array<int, OmnibusPrice>
     */
    public function findLowestEffectiveBatch(array $productIds, int $days = 30): array
    {
        ++$this->batchCallCount;
        $this->lastBatchIds = array_values($productIds);

        return $this->batchResult;
    }
}
