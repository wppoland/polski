<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\SearchService;

/**
 * The dropdown and the results page must agree about what counts as a match.
 * They stopped agreeing because the extra IDs were spliced into the wrong
 * parenthesis for logged-out visitors, which is everyone who shops.
 */
final class SearchClauseTest extends TestCase
{
    /**
     * @param list<int> $ids
     */
    private function widen(string $search, array $ids): string
    {
        $method = new \ReflectionMethod(SearchService::class, 'widenSearchClause');
        $method->setAccessible(true);

        return $method->invoke(
            (new \ReflectionClass(SearchService::class))->newInstanceWithoutConstructor(),
            $search,
            $ids,
            'wp_posts',
        );
    }

    public function testLoggedOutSearchIsWidenedAndThePasswordClauseStaysIntact(): void
    {
        // Exactly what WP_Query::parse_search returns for a logged-out visitor.
        $search = " AND (((wp_posts.post_title LIKE '%woda%'))) "
            . " AND (wp_posts.post_password = '') ";

        $widened = $this->widen($search, [12, 34]);

        self::assertStringContainsString(
            "LIKE '%woda%')) OR (wp_posts.ID IN (12,34))",
            $widened,
            'the extra IDs belong inside the search clause',
        );
        self::assertStringEndsWith(" AND (wp_posts.post_password = '') ", $widened);
        self::assertStringNotContainsString("post_password = '' OR", $widened);
    }

    public function testLoggedInSearchIsWidenedToo(): void
    {
        $search = " AND (((wp_posts.post_title LIKE '%woda%'))) ";

        self::assertStringContainsString(
            "OR (wp_posts.ID IN (12))",
            $this->widen($search, [12]),
        );
    }

    public function testIdsAreForcedToIntegers(): void
    {
        $search = " AND ((wp_posts.post_title LIKE '%x%')) ";

        self::assertStringContainsString('IN (7,0)', $this->widen($search, [7, 0]));
    }
}
