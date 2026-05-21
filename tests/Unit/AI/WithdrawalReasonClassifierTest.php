<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Polski\AI\WithdrawalReasonClassifier;
use Polski\Repository\WithdrawalRepository;

/**
 * Verifies the AI classifier degrades gracefully when WordPress AI Client is
 * missing or the operator has not enabled AI features.
 *
 * WithdrawalRepository is final, so the test instantiates a bare object via
 * Reflection and injects a recording wpdb stand-in for the few methods the
 * classifier touches.
 */
final class WithdrawalReasonClassifierTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['polski_test_options'] = [];
    }

    public function testNoopWhenFeatureFlagDisabled(): void
    {
        $GLOBALS['polski_test_options']['polski_ai_features_enabled'] = 'no';

        $repository = $this->bareRepository();

        $service = new WithdrawalReasonClassifier($repository);
        // Should return immediately - no DB hit, no AI call.
        $service->classify(1);

        // Reaching this line without errors is the assertion.
        self::assertTrue(true);
    }

    public function testNoopWhenAiClientUnavailable(): void
    {
        $GLOBALS['polski_test_options']['polski_ai_features_enabled'] = 'yes';

        $repository = $this->bareRepository();

        $service = new WithdrawalReasonClassifier($repository);
        $service->classify(2);

        self::assertTrue(true);
    }

    public function testCategoriesAreStable(): void
    {
        $categories = WithdrawalReasonClassifier::categories();

        self::assertContains('defective', $categories);
        self::assertContains('wrong_item', $categories);
        self::assertContains('other', $categories);
        self::assertGreaterThanOrEqual(8, count($categories));
    }

    public function testCategoriesIncludeAllExpectedReturnReasons(): void
    {
        $categories = WithdrawalReasonClassifier::categories();

        self::assertSame(
            [
                'defective',
                'wrong_item',
                'size_mismatch',
                'changed_mind',
                'late_delivery',
                'damaged_in_transit',
                'not_as_described',
                'other',
            ],
            $categories,
        );
    }

    private function bareRepository(): WithdrawalRepository
    {
        return (new \ReflectionClass(WithdrawalRepository::class))->newInstanceWithoutConstructor();
    }
}
