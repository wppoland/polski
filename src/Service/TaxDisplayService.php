<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Enum\TaxDisplayMode;
use Polski\Util\Formatter;

/**
 * Handles tax display logic: brutto/netto toggle, VAT notices, small business exemption.
 */
final class TaxDisplayService
{
    public function getMode(): TaxDisplayMode
    {
        $settings = $this->getSettings();
        $mode = $settings['tax_display_mode'] ?? 'brutto';

        return TaxDisplayMode::tryFrom($mode) ?? TaxDisplayMode::Brutto;
    }

    public function isSmallBusiness(): bool
    {
        $general = get_option('polski_general', []);
        return is_array($general) && (bool) ($general['small_business'] ?? false);
    }

    /**
     * Get the VAT notice HTML for a product.
     */
    public function getVatNoticeHtml(\WC_Product $product): string
    {
        if ($this->isSmallBusiness()) {
            $text = $this->getSettings()['vat_exempt_notice'] ?? '';

            if ($text === '') {
                return '';
            }

            $html = sprintf(
                '<span class="polski-tax-info polski-tax-info--exempt">%s</span>',
                esc_html($text),
            );

            return (string) apply_filters('polski/price/vat_notice', $html, $product);
        }

        $taxRates = \WC_Tax::get_rates($product->get_tax_class());

        if (empty($taxRates)) {
            return '';
        }

        $rate = reset($taxRates);
        $ratePercent = (float) ($rate['rate'] ?? 0);

        $template = $this->getSettings()['vat_notice_text'] ?? 'w tym {rate}% VAT';

        $text = Formatter::interpolate($template, [
            'rate' => Formatter::vatRate($ratePercent),
        ]);

        $html = sprintf(
            '<span class="polski-tax-info">%s</span>',
            esc_html($text),
        );

        return (string) apply_filters('polski/price/vat_notice', $html, $product);
    }

    /**
     * Get the shipping costs notice HTML.
     */
    public function getShippingNoticeHtml(): string
    {
        $priceSettings = get_option('polski_prices', []);

        if (! is_array($priceSettings) || ! ($priceSettings['shipping_costs_notice_enabled'] ?? true)) {
            return '';
        }

        $text = $priceSettings['shipping_costs_text'] ?? '';

        if ($text === '') {
            return '';
        }

        $shippingPageId = wc_get_page_id('shop');
        $shippingUrl = get_permalink($shippingPageId);

        $html = sprintf(
            '<span class="polski-shipping-notice" style="margin-left:0.35em"><a href="%s" target="_blank" rel="noopener">%s</a></span>',
            esc_url($shippingUrl ?: '#'),
            esc_html($text),
        );

        return (string) apply_filters('polski/price/shipping_notice', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        $settings = get_option('polski_taxes', []);
        return is_array($settings) ? $settings : [];
    }
}
