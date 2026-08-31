<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Hook;

use PHPUnit\Framework\TestCase;
use Polski\Hook\CheckoutHooks;
use Polski\Repository\ConsentLogRepository;
use Polski\Service\CheckboxService;
use Polski\Util\TemplateLoader;

final class CheckoutHooksTest extends TestCase
{
    private CheckoutHooks $hooks;

    protected function setUp(): void
    {
        $checkboxes = new CheckboxService();
        $consentLog = new ConsentLogRepository(new \wpdb());
        $templates = new TemplateLoader();
        $this->hooks = new CheckoutHooks($checkboxes, $consentLog, $templates);
    }

    public function testFilterOrderButtonTextReturnsDefaultStatutoryWordingWhenSettingEmpty(): void
    {
        \delete_option('polski_checkout');

        $result = $this->hooks->filterOrderButtonText('Kupuję i płacę');
        self::assertSame('Order with an obligation to pay', $result);
    }

    public function testFilterOrderButtonTextUsesCustomSettingWhenConfigured(): void
    {
        \update_option('polski_checkout', ['order_button_text' => 'Kup teraz i zapłać']);

        $result = $this->hooks->filterOrderButtonText('Kupuję i płacę');
        self::assertSame('Kup teraz i zapłać', $result);

        \delete_option('polski_checkout');
    }

    /**
     * The block checkout gets its label through WooCommerce's own
     * `placeOrderButtonLabel` checkout filter, fed from PHP by
     * orderButtonLabel(). This used to be attempted with a `gettext` filter,
     * which the tests happily proved worked while the button on a real block
     * checkout kept WooCommerce's own wording: PHP gettext is never called for
     * a string the React bundle translates.
     */
    public function testOrderButtonLabelFeedsTheBlockCheckoutFilter(): void
    {
        \delete_option('polski_checkout');
        self::assertSame('Order with an obligation to pay', $this->hooks->orderButtonLabel());

        \update_option('polski_checkout', ['order_button_text' => 'Kup teraz i zapłać']);
        self::assertSame('Kup teraz i zapłać', $this->hooks->orderButtonLabel());

        \delete_option('polski_checkout');
    }
}
