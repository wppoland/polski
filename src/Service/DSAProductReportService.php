<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Util\TemplateLoader;

/**
 * Per-product DSA illegal content report widget.
 *
 * Renders an unobtrusive "Zgłoś nielegalne treści" trigger on single product
 * pages. The trigger expands a <details> element holding the existing DSA
 * report form with the product permalink prefilled. The form posts to the
 * existing admin-post handler in DSAService - no new endpoint needed.
 *
 * Settings live alongside the DSA module options in the polski_dsa group
 * so admins manage both surfaces in one place:
 *
 *   - product_widget_enabled (bool, default false)
 *   - product_widget_position (enum, default after_summary)
 */
final class DSAProductReportService
{
    public const OPTION_GROUP = 'polski_dsa';
    private const HOOK_AFTER_SUMMARY = 'woocommerce_after_single_product_summary';
    private const HOOK_PRODUCT_META = 'woocommerce_product_meta_end';

    public function __construct(
        private readonly DSAService $dsa,
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function isWidgetEnabled(): bool
    {
        if (! $this->dsa->isEnabled()) {
            return false;
        }

        $settings = get_option(self::OPTION_GROUP, []);
        $enabled = is_array($settings) && array_key_exists('product_widget_enabled', $settings)
            ? (bool) $settings['product_widget_enabled']
            : false;

        /**
         * Filter master switch for the per-product DSA report widget.
         *
         * @param bool $enabled Whether the widget is rendered on product pages.
         */
        return (bool) apply_filters('polski/dsa/product_widget_enabled', $enabled);
    }

    public function widgetHook(): string
    {
        $settings = get_option(self::OPTION_GROUP, []);
        $position = is_array($settings) && isset($settings['product_widget_position'])
            ? (string) $settings['product_widget_position']
            : 'after_summary';

        return match ($position) {
            'product_meta' => self::HOOK_PRODUCT_META,
            default => self::HOOK_AFTER_SUMMARY,
        };
    }

    public function render(): void
    {
        if (! $this->isWidgetEnabled()) {
            return;
        }

        if (! function_exists('is_product') || ! is_product()) {
            return;
        }

        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $permalink = (string) $product->get_permalink();
        $name = (string) $product->get_name();

        if ($permalink === '') {
            return;
        }

        $formHtml = $this->renderPrefilledForm($permalink, $name);
        if ($formHtml === '') {
            return;
        }

        echo '<div class="polski-dsa-product-report">';
        echo '<details class="polski-dsa-product-report__details">';
        echo '<summary class="polski-dsa-product-report__trigger">';
        echo esc_html__('Zgłoś nielegalne treści (DSA)', 'polski');
        echo '</summary>';
        echo '<div class="polski-dsa-product-report__form">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered template, escaping internal.
        echo $formHtml;
        echo '</div>';
        echo '</details>';
        echo '</div>';
    }

    private function renderPrefilledForm(string $url, string $label): string
    {
        $settings = get_option(self::OPTION_GROUP, []);

        ob_start();
        $this->templateLoader->include('forms/dsa-report', [
            'settings' => is_array($settings) ? $settings : [],
            'prefill_url' => $url,
            'prefill_label' => $label,
        ]);

        return (string) ob_get_clean();
    }
}
