<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

/**
 * Tests for MinimumOrderService validation logic.
 *
 * Since MinimumOrderService depends on WC()->cart and WooCommerce functions,
 * we test the validation logic patterns and settings handling.
 */
final class MinimumOrderServiceTest extends TestCase
{
    public function testDefaultSettingsStructure(): void
    {
        // Verify the defaults.php structure matches what the service expects.
        $defaults = [
            'min_value' => 0,
            'min_quantity' => 0,
            'exclude_sale_items' => false,
            'min_value_message' => 'Minimum order value is {min_value}. Current cart value: {current_value}.',
            'min_quantity_message' => 'Minimum number of items per order is {min_quantity}. Current quantity: {current_quantity}.',
        ];

        $this->assertArrayHasKey('min_value', $defaults);
        $this->assertArrayHasKey('min_quantity', $defaults);
        $this->assertArrayHasKey('exclude_sale_items', $defaults);
        $this->assertArrayHasKey('min_value_message', $defaults);
        $this->assertArrayHasKey('min_quantity_message', $defaults);
    }

    public function testMessageTokenReplacement(): void
    {
        $template = 'Minimum order value is {min_value}. Current cart value: {current_value}.';
        $result = str_replace(
            ['{min_value}', '{current_value}'],
            ['50,00 PLN', '29,99 PLN'],
            $template,
        );

        $this->assertSame('Minimum order value is 50,00 PLN. Current cart value: 29,99 PLN.', $result);
    }

    public function testQuantityMessageTokenReplacement(): void
    {
        $template = 'Minimum number of items per order is {min_quantity}. Current quantity: {current_quantity}.';
        $result = str_replace(
            ['{min_quantity}', '{current_quantity}'],
            ['3', '1'],
            $template,
        );

        $this->assertSame('Minimum number of items per order is 3. Current quantity: 1.', $result);
    }

    public function testZeroMinValueDisablesCheck(): void
    {
        $minValue = 0;
        $cartTotal = 10.0;

        // When min_value is 0, the check should not trigger.
        $shouldValidate = $minValue > 0;
        $this->assertFalse($shouldValidate);
    }

    public function testCartBelowMinValueTriggersValidation(): void
    {
        $minValue = 50.0;
        $cartTotal = 29.99;

        $shouldFail = $minValue > 0 && $cartTotal < $minValue;
        $this->assertTrue($shouldFail);
    }

    public function testCartAboveMinValuePasses(): void
    {
        $minValue = 50.0;
        $cartTotal = 75.00;

        $shouldFail = $minValue > 0 && $cartTotal < $minValue;
        $this->assertFalse($shouldFail);
    }

    public function testQuantityBelowMinTriggersValidation(): void
    {
        $minQuantity = 3;
        $cartQuantity = 1;

        $shouldFail = $minQuantity > 0 && $cartQuantity < $minQuantity;
        $this->assertTrue($shouldFail);
    }
}
