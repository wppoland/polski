<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Enum\PriceType;
use Polski\Model\OmnibusPrice;
use Polski\Repository\OmnibusPriceRepository;
use Polski\Util\Formatter;

/**
 * Omnibus Directive compliance - tracks product prices and displays the lowest
 * price from the last 30 days when a product is on sale.
 *
 * Acts as a built-in fallback. When a compatible Omnibus extension is
 * detected, the IntegrationManager delegates to that integration instead.
 */
final class OmnibusService implements Bootable, HasHooks
{
    private bool $enabled = true;
    private int $days = 30;
    private string $displayText = '';
    private bool $saleOnly = true;

    public function __construct(
        private readonly OmnibusPriceRepository $repository,
    ) {
    }

    public function boot(): void
    {
        $settings = $this->getSettings();
        $this->enabled = (bool) ($settings['enabled'] ?? true);
        $this->days = (int) ($settings['days'] ?? 30);
        $this->displayText = (string) ($settings['display_text'] ?? __('Lowest price from the last {days} days: {price}', 'polski'));
        $this->saleOnly = (bool) ($settings['display_on_sale_only'] ?? true);
    }

    public function registerHooks(): void
    {
        if (! $this->enabled) {
            return;
        }

        // Record prices when products are saved.
        add_action('woocommerce_update_product', [$this, 'onProductSave'], 10, 2);
        add_action('woocommerce_new_product', [$this, 'onProductSave'], 10, 2);

        // Record price on variation save.
        add_action('woocommerce_save_product_variation', [$this, 'onVariationSave'], 10, 2);

        // Daily cleanup.
        add_action('polski_daily_maintenance', [$this, 'pruneOldRecords']);
    }

    /**
     * Record price when a product is saved.
     */
    public function onProductSave(int $productId, \WC_Product $product): void
    {
        if ($product->is_type('variable')) {
            return; // Variations are handled separately.
        }

        $this->recordProductPrice($product);
    }

    /**
     * Record price when a variation is saved.
     */
    public function onVariationSave(int $variationId, int $loop): void
    {
        $variation = wc_get_product($variationId);

        if (! $variation instanceof \WC_Product) {
            return;
        }

        $this->recordProductPrice($variation);
    }

    /**
     * Record the current price of a product.
     */
    public function recordProductPrice(\WC_Product $product): void
    {
        $regularPrice = (float) $product->get_regular_price();

        if ($regularPrice <= 0) {
            return;
        }

        $salePrice = $product->get_sale_price();
        $saleFloat = $salePrice !== '' ? (float) $salePrice : null;

        $priceType = $saleFloat !== null ? PriceType::Sale : PriceType::Regular;

        // Avoid duplicate recordings on the same day.
        if ($this->repository->hasRecordedToday($product->get_id())) {
            return;
        }

        $currency = get_woocommerce_currency();

        $this->repository->recordPrice(
            $product->get_id(),
            $regularPrice,
            $saleFloat,
            $priceType,
            $currency,
        );

        /**
         * Fires after a price is recorded for Omnibus tracking.
         *
         * @param int   $productId The product ID.
         * @param float $price     The regular price.
         * @param ?float $salePrice The sale price, or null.
         */
        do_action('polski/omnibus/price_recorded', $product->get_id(), $regularPrice, $saleFloat);
    }

    /**
     * Get the lowest price in the tracking period for a product.
     */
    public function getLowestPrice(int $productId): ?OmnibusPrice
    {
        return $this->repository->findLowestEffective($productId, $this->days);
    }

    /**
     * Get formatted HTML for the Omnibus lowest price notice.
     */
    public function getLowestPriceHtml(int $productId): string
    {
        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product) {
            return '';
        }

        // Only show on sale products if configured.
        if ($this->saleOnly && ! $product->is_on_sale()) {
            return '';
        }

        $lowest = $this->getLowestPrice($productId);

        if ($lowest === null) {
            return '';
        }

        $priceHtml = wc_price($lowest->effectivePrice(), ['currency' => $lowest->currency]);

        $text = Formatter::interpolate($this->displayText, [
            'price' => wp_strip_all_tags($priceHtml),
            'days' => (string) $this->days,
        ]);

        $html = sprintf(
            '<div class="polski-omnibus-price"><span class="polski-omnibus-price__text">%s</span></div>',
            esc_html($text),
        );

        /**
         * Filter the Omnibus price HTML.
         *
         * @param string        $html    The HTML output.
         * @param ?OmnibusPrice $lowest  The lowest price record.
         * @param \WC_Product   $product The product.
         */
        return (string) apply_filters('polski/price/omnibus_html', $html, $lowest, $product);
    }

    /**
     * Get the price history for a product.
     *
     * @return list<OmnibusPrice>
     */
    public function getPriceHistory(int $productId): array
    {
        return $this->repository->findHistory($productId, $this->days);
    }

    /**
     * Check if a product is currently on sale.
     */
    public function isOnSale(int $productId): bool
    {
        $product = wc_get_product($productId);
        return $product instanceof \WC_Product && $product->is_on_sale();
    }

    /**
     * Prune old price records (called via cron).
     */
    public function pruneOldRecords(): void
    {
        $settings = $this->getSettings();
        $days = (int) ($settings['prune_after_days'] ?? 90);
        $deleted = $this->repository->deleteOlderThan($days);

        /**
         * Fires after old Omnibus price records are pruned.
         *
         * @param int $deleted Number of records deleted.
         */
        do_action('polski/omnibus/history_pruned', $deleted);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        $settings = get_option('polski_omnibus', []);
        return is_array($settings) ? $settings : [];
    }
}
