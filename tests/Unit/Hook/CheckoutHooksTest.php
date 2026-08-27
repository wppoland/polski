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
        self::assertSame('Zamawiam z obowiązkiem zapłaty', $result);
    }

    public function testFilterOrderButtonTextUsesCustomSettingWhenConfigured(): void
    {
        \update_option('polski_checkout', ['order_button_text' => 'Kup teraz i zapłać']);

        $result = $this->hooks->filterOrderButtonText('Kupuję i płacę');
        self::assertSame('Kup teraz i zapłać', $result);

        \delete_option('polski_checkout');
    }

    public function testFilterBlockOrderButtonTextTranslatesPlaceOrderOnBlockCheckout(): void
    {
        \delete_option('polski_checkout');

        $result = $this->hooks->filterBlockOrderButtonText('Place order', 'Place order', 'woocommerce');
        self::assertSame('Zamawiam z obowiązkiem zapłaty', $result);

        $resultPL = $this->hooks->filterBlockOrderButtonText('Kupuję i płacę', 'Place order', 'woocommerce');
        self::assertSame('Zamawiam z obowiązkiem zapłaty', $resultPL);
    }

    public function testFilterBlockOrderButtonTextIgnoresOtherDomains(): void
    {
        $result = $this->hooks->filterBlockOrderButtonText('Place order', 'Place order', 'other-plugin');
        self::assertSame('Place order', $result);
    }
}
