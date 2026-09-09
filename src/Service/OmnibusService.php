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
 * This is the built-in implementation. A dedicated Omnibus extension can
 * replace the rendered output through the polski/price/omnibus_html filter.
 */
final class OmnibusService implements Bootable, HasHooks
{
    private const CACHE_GROUP = 'polski_omnibus';
    private const CACHE_TTL = 3600; // 1 hour - product prices change rarely; invalidated on save.

    private bool $enabled = true;
    private int $days = 30;
    private string $displayText = '';
    private bool $saleOnly = true;
    private bool $includeTax = true;
    private bool $showRegularPrice = false;
    private string $noHistoryMode = 'hide';
    private string $noHistoryText = '';
    private string $countFrom = 'sale_start';

    public function __construct(
        private readonly OmnibusPriceRepository $repository,
    ) {
    }

    public function boot(): void
    {
        $settings = $this->getSettings();
        $this->enabled = (bool) ($settings['enabled'] ?? true);
        $this->days = (int) ($settings['days'] ?? 30);
        if ($this->days < 1) {
            // A stored empty/zero value would collapse the legally required
            // lowest-price window to the current price; fall back to 30 days.
            $this->days = 30;
        }
        $this->displayText = (string) ($settings['display_text'] ?? __('Lowest price from the last {days} days: {price}', 'polski'));
        $this->saleOnly = (bool) ($settings['display_on_sale_only'] ?? true);
        $this->includeTax = (bool) ($settings['include_tax'] ?? true);
        // Declared default on the modules screen is false; a truthy fallback
        // would switch a new line on for every shop that never opened the form.
        $this->showRegularPrice = (bool) ($settings['show_regular_price'] ?? false);

        $mode = (string) ($settings['no_history_text'] ?? 'hide');
        // 'hide' is what the code did unconditionally before this was wired up,
        // so a shop that never touched the field sees no change.
        $this->noHistoryMode = in_array($mode, ['hide', 'current', 'custom'], true) ? $mode : 'hide';
        $this->noHistoryText = (string) ($settings['no_history_custom_text'] ?? '');

        $countFrom = (string) ($settings['price_count_from'] ?? 'sale_start');
        $this->countFrom = in_array($countFrom, ['sale_start', 'today'], true) ? $countFrom : 'sale_start';
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

        // Warm the per-product lowest-price cache once at the top of every
        // WooCommerce archive loop so individual product callbacks downstream
        // hit the object cache instead of running their own DB query.
        add_action('woocommerce_before_shop_loop', [$this, 'warmCacheForArchive'], 5);
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

        $this->invalidateLowestPriceCache($product->get_id());

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
     * End of the comparison window for a product, or null for "up to now".
     *
     * The Omnibus Directive asks for the lowest price in the 30 days *before the
     * reduction*, so the window should stop where the sale starts: otherwise the
     * running sale price competes to be its own lowest price and the notice just
     * repeats the current price.
     *
     * Returns null whenever the sale has no start date, which is the common case
     * of a merchant typing a sale price without scheduling it. There is nothing
     * to anchor to then, so the window ends now, exactly as before.
     */
    private function referenceCutoff(\WC_Product $product): ?string
    {
        if ($this->countFrom !== 'sale_start') {
            return null;
        }

        $from = $product->get_date_on_sale_from();

        return $from instanceof \WC_DateTime ? gmdate('Y-m-d H:i:s', $from->getTimestamp()) : null;
    }

    /**
     * Get the lowest price in the tracking period for a product.
     *
     * Cached per-product in the WP object cache for one hour. On stores with
     * a persistent object cache (memcached / redis) this turns a 12-product
     * archive render from 12 lookups against polski_price_history into 12
     * cache hits. The cache is invalidated whenever a new price is recorded
     * for the product (see onProductSave -> invalidateLowestPriceCache).
     */
    public function getLowestPrice(int $productId, ?string $before = null): ?OmnibusPrice
    {
        $cacheKey = (string) $productId . ':' . (string) $this->days . ':' . ($before ?? 'now');

        $found = false;
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP, false, $found);

        if ($found) {
            if ($cached === null || $cached instanceof OmnibusPrice) {
                /** @var ?OmnibusPrice */
                return $cached;
            }
        }

        $result = $this->repository->findLowestEffective($productId, $this->days, $before);

        wp_cache_set($cacheKey, $result, self::CACHE_GROUP, self::CACHE_TTL);

        return $result;
    }

    /**
     * Forget the cached lowest price for a product. Called after a new price
     * snapshot is recorded so the next read sees the fresh window.
     */
    private function invalidateLowestPriceCache(int $productId): void
    {
        // Two key shapes exist: ':now' for the open window and ':<date>' when the
        // window ends at a sale start. The sale-start key cannot be recomputed
        // here without loading the product, so drop the group's cache generation
        // as well; wp_cache_delete alone would leave a stale windowed entry.
        wp_cache_delete((string) $productId . ':' . (string) $this->days . ':now', self::CACHE_GROUP);

        $product = wc_get_product($productId);

        if ($product instanceof \WC_Product) {
            $before = $this->referenceCutoff($product);
            if ($before !== null) {
                wp_cache_delete((string) $productId . ':' . (string) $this->days . ':' . $before, self::CACHE_GROUP);
            }
        }
    }

    /**
     * Pre-warm the per-product cache for every product about to be rendered in
     * a WooCommerce archive. One SQL query against polski_price_history
     * replaces the N separate queries that would otherwise fire as each
     * product callback in the loop reads its own lowest price.
     *
     * Products without a recorded history in the window are still cached -
     * as `null` - so the next read short-circuits without re-querying.
     */
    public function warmCacheForArchive(): void
    {
        global $wp_query;

        // Each product has its own sale start, so one shared window cannot serve
        // them. Warming would only fill ':now' keys nothing then reads.
        if ($this->countFrom === 'sale_start') {
            return;
        }

        if (! $wp_query instanceof \WP_Query || empty($wp_query->posts)) {
            return;
        }

        $productIds = [];
        foreach ($wp_query->posts as $post) {
            if ($post instanceof \WP_Post && $post->post_type === 'product') {
                $productIds[] = (int) $post->ID;
            }
        }

        if ($productIds === []) {
            return;
        }

        $days = (string) $this->days;
        $uncached = [];

        foreach (array_unique($productIds) as $id) {
            $found = false;
            wp_cache_get((string) $id . ':' . $days . ':now', self::CACHE_GROUP, false, $found);
            if (! $found) {
                $uncached[] = $id;
            }
        }

        if ($uncached === []) {
            return;
        }

        $batch = $this->repository->findLowestEffectiveBatch($uncached, $this->days);

        foreach ($uncached as $id) {
            wp_cache_set(
                (string) $id . ':' . $days,
                $batch[$id] ?? null,
                self::CACHE_GROUP,
                self::CACHE_TTL,
            );
        }
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

        $lowest = $this->getLowestPrice($productId, $this->referenceCutoff($product));

        if ($lowest === null) {
            // No recorded history. Saying "the price has not changed" would be a
            // claim we cannot support: a fresh install has no history either.
            if ($this->noHistoryMode === 'hide') {
                return '';
            }

            // Same conversion as the lowest-price path, or a shop entering
            // prices net would see a net figure here and a gross one there.
            $amount = $this->priceForDisplay($product, (float) $product->get_price());
            $template = $this->noHistoryMode === 'custom' && trim($this->noHistoryText) !== ''
                ? $this->noHistoryText
                : $this->displayText;
            $currency = get_woocommerce_currency();
        } else {
            $amount = $this->priceForDisplay($product, $lowest->effectivePrice());
            $template = $this->displayText;
            $currency = $lowest->currency;
        }

        $priceHtml = wc_price($amount, ['currency' => $currency]);

        $text = Formatter::interpolate($template, [
            'price' => wp_strip_all_tags($priceHtml),
            'days' => (string) $this->days,
        ]);

        // Only worth saying when there is actually a higher price to compare to.
        if ($this->showRegularPrice && $lowest !== null && $lowest->price > $lowest->effectivePrice()) {
            $text .= ' ' . sprintf(
                /* translators: %s: the product's regular price before the reduction */
                __('Regular price: %s', 'polski'),
                wp_strip_all_tags(wc_price($this->priceForDisplay($product, $lowest->price), ['currency' => $lowest->currency])),
            );
        }

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
     * Convert a stored price for display.
     *
     * History is recorded exactly as the merchant entered it, so a shop that
     * enters prices excluding tax was showing a net figure in the notice next to
     * a gross selling price. These two helpers normalise against
     * `woocommerce_prices_include_tax`, so they are right whichever way prices
     * are entered, and they are no-ops when tax is off or the product is not
     * taxable.
     */
    private function priceForDisplay(\WC_Product $product, float $amount): float
    {
        return $this->includeTax
            ? (float) wc_get_price_including_tax($product, ['price' => $amount])
            : (float) wc_get_price_excluding_tax($product, ['price' => $amount]);
    }

    /**
     * The same notice as {@see self::getLowestPriceHtml()} but as plain text.
     *
     * The cart needs this. Both carts render the line through
     * `woocommerce_get_item_data`, and the block cart takes that array over the
     * Store API and renders it as React nodes, so any markup would be shown as
     * literal characters rather than parsed.
     */
    public function getLowestPriceText(int $productId): string
    {
        $html = $this->getLowestPriceHtml($productId);

        if ($html === '') {
            return '';
        }

        // wc_price() encodes the currency symbol, so stripping tags leaves
        // "&#036;79.00". The classic cart would render that as a symbol, but the
        // block cart passes item_data through React, which escapes it and shows
        // the entity literally. Decode before either sees it.
        return trim(html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8'));
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
