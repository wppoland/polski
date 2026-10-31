<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Util\BlockLoop;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

/**
 * Product badges for merchandising and conversion hints.
 */
final class BadgeService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_badges';

    public function __construct(
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('woocommerce_before_single_product_summary', [$this, 'renderSingleBadges'], 6);
        add_action('woocommerce_before_shop_loop_item_title', [$this, 'renderLoopBadges'], 9);
        add_filter('woocommerce_sale_flash', [$this, 'filterSaleFlash']);

        // Block themes: the summary hook fires before the first template block
        // (above the breadcrumbs), and Product Collection cards outside the
        // shop archive fire no loop hook. Both go on the image block instead.
        add_filter('render_block_woocommerce/product-image-gallery', [$this, 'prependSingleBadgesToGallery'], 10, 3);
        add_filter('render_block_woocommerce/product-image', [$this, 'prependLoopBadgesToImage'], 10, 3);
        add_filter('render_block_woocommerce/product-sale-badge', [$this, 'filterSaleBadgeBlock']);
    }

    /**
     * The Product Sale Badge block never passes through `woocommerce_sale_flash`,
     * so on a block theme it stood next to our own Sale badge.
     */
    public function filterSaleBadgeBlock(mixed $html): mixed
    {
        return is_string($html) && $html !== '' && $this->filterSaleFlash($html) === '' ? '' : $html;
    }

    /**
     * @param string|mixed $content
     */
    public function prependSingleBadgesToGallery(mixed $content, mixed $block = null, mixed $instance = null): mixed
    {
        if (! is_string($content) || ! wp_is_block_theme() || ! is_product()) {
            return $content;
        }

        $product = BlockLoop::product($instance) ?? wc_get_product(get_queried_object_id());

        if (! $product instanceof \WC_Product || ! ($this->getSettings()['show_on_single'] ?? true)) {
            return $content;
        }

        return BlockLoop::prependInside($content, BlockLoop::render($product, fn () => $this->render($product, 'single')));
    }

    /**
     * @param string|mixed               $content
     * @param array<string, mixed>|mixed $block
     */
    public function prependLoopBadgesToImage(mixed $content, mixed $block, mixed $instance = null): mixed
    {
        if (! is_string($content) || $content === '' || BlockLoop::classicHooksCover($block)) {
            return $content;
        }

        $product = BlockLoop::product($instance);

        if (! $product instanceof \WC_Product || ! ($this->getSettings()['show_on_loop'] ?? true)) {
            return $content;
        }

        return BlockLoop::prependInside($content, BlockLoop::render($product, fn () => $this->render($product, 'loop')));
    }

    /**
     * Our Sale (or discount) badge replaces WooCommerce's own flash; showing
     * both put two sale labels on the same product image.
     */
    public function filterSaleFlash(string $html): string
    {
        $settings = $this->getSettings();
        $ours = (bool) ($settings['show_sale_badge'] ?? true) || (bool) ($settings['show_discount_percent_badge'] ?? false);
        $shown = is_product() ? ($settings['show_on_single'] ?? true) : ($settings['show_on_loop'] ?? true);

        return $ours && $shown ? '' : $html;
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('badge_management');
    }

    public function enqueueAssets(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        wp_register_style(
            'polski-badges',
            \Polski\Plugin::instance()->url('assets/css/badges.css'),
            [],
            \Polski\VERSION,
        );

        // Head load on shop screens; a badge rendered anywhere else (a product
        // block on a plain page) enqueues the stylesheet from render().
        if (function_exists('is_woocommerce') && (is_woocommerce() || is_cart() || is_checkout())) {
            wp_enqueue_style('polski-badges');
        }
    }

    public function renderSingleBadges(): void
    {
        global $product;

        // A block theme gets these inside the gallery block instead.
        if (! $product instanceof \WC_Product || ! ($this->getSettings()['show_on_single'] ?? true) || wp_is_block_theme()) {
            return;
        }

        $this->render($product, 'single');
    }

    public function renderLoopBadges(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! ($this->getSettings()['show_on_loop'] ?? true)) {
            return;
        }

        $this->render($product, 'loop');
    }

    /**
     * @return list<array{text: string, style: string}>
     */
    public function getBadges(\WC_Product $product, string $context = ''): array
    {
        $settings = $this->getSettings();
        $badges = [];

        $manual = trim((string) $product->get_meta('_polski_badge_text', true));
        $manualStyle = sanitize_key((string) $product->get_meta('_polski_badge_style', true));
        $secondary = trim((string) $product->get_meta('_polski_badge_secondary_text', true));

        if ((bool) ($settings['show_manual_badge'] ?? true) && $manual !== '') {
            $badges[] = ['text' => $manual, 'style' => $manualStyle !== '' ? $manualStyle : (string) ($settings['manual_badge_style'] ?? 'accent')];
        }

        if ((bool) ($settings['show_secondary_badge'] ?? true) && $secondary !== '') {
            $badges[] = ['text' => $secondary, 'style' => (string) ($settings['secondary_badge_style'] ?? 'neutral')];
        }

        if ((bool) ($settings['show_sale_badge'] ?? true) && $product->is_on_sale()) {
            $badges[] = ['text' => (string) ($settings['sale_badge_text'] ?? __('Sale', 'polski')), 'style' => 'warning'];
        }

        if ((bool) ($settings['show_new_badge'] ?? true) && $this->isNew($product)) {
            $badges[] = ['text' => (string) ($settings['new_badge_text'] ?? __('New', 'polski')), 'style' => 'success'];
        }

        if ((bool) ($settings['show_low_stock_badge'] ?? true) && $this->isLowStock($product)) {
            $badges[] = ['text' => (string) ($settings['low_stock_badge_text'] ?? __('Last items', 'polski')), 'style' => 'warning'];
        }

        if ((bool) ($settings['show_bestseller_badge'] ?? true) && $this->isBestseller($product)) {
            $badges[] = ['text' => (string) ($settings['bestseller_badge_text'] ?? __('Bestseller', 'polski')), 'style' => 'accent'];
        }

        // Discount percentage badge (e.g. "-25%").
        if ((bool) ($settings['show_discount_percent_badge'] ?? false) && $product->is_on_sale()) {
            $discount = $this->getDiscountPercent($product);
            if ($discount > 0) {
                $badges[] = ['text' => '-' . $discount . '%', 'style' => 'danger'];
            }
        }

        // Free shipping badge.
        if ((bool) ($settings['show_free_shipping_badge'] ?? false)) {
            $shippingClass = $product->get_shipping_class();
            $freeShippingClasses = array_filter(array_map('trim', explode(',', (string) ($settings['free_shipping_classes'] ?? 'free-shipping'))));
            if (in_array($shippingClass, $freeShippingClasses, true)) {
                $badges[] = ['text' => (string) ($settings['free_shipping_badge_text'] ?? __('Free shipping', 'polski')), 'style' => 'success'];
            }
        }

        // Out of stock badge.
        if ((bool) ($settings['show_out_of_stock_badge'] ?? true) && ! $product->is_in_stock()) {
            $badges[] = ['text' => (string) ($settings['out_of_stock_badge_text'] ?? __('Out of stock', 'polski')), 'style' => 'neutral'];
        }

        $unique = [];

        foreach ($badges as $badge) {
            $key = $badge['text'] . '|' . $badge['style'];
            $unique[$key] = $badge;
        }

        $badges = array_values($unique);
        $limit = $this->getBadgeLimit($context);

        return array_slice($badges, 0, $limit);
    }

    private function render(\WC_Product $product, string $context): void
    {
        $badges = $this->getBadges($product, $context);

        if ($badges === []) {
            return;
        }

        wp_enqueue_style('polski-badges');

        $this->templateLoader->include('shared/badges', [
            'badges' => $badges,
            'context' => $context,
            'product' => $product,
            'shape' => (string) ($this->getSettings()['shape'] ?? 'pill'),
            'uppercase' => (bool) ($this->getSettings()['uppercase'] ?? false),
        ]);
    }

    private function getBadgeLimit(string $context = ''): int
    {
        if ($context === 'single' || ($context === '' && is_product())) {
            return max(1, (int) ($this->getSettings()['max_badges_single'] ?? 4));
        }

        return max(1, (int) ($this->getSettings()['max_badges_loop'] ?? 3));
    }

    private function isNew(\WC_Product $product): bool
    {
        $days = max(1, (int) ($this->getSettings()['newness_days'] ?? 30));
        $created = $product->get_date_created();

        if (! $created instanceof \WC_DateTime) {
            return false;
        }

        return $created->getTimestamp() >= strtotime('-' . $days . ' days');
    }

    private function isLowStock(\WC_Product $product): bool
    {
        if (! $product->managing_stock()) {
            return false;
        }

        $quantity = $product->get_stock_quantity();
        $threshold = max(1, (int) ($this->getSettings()['low_stock_threshold'] ?? 3));

        return $quantity !== null && $quantity > 0 && $quantity <= $threshold;
    }

    private function isBestseller(\WC_Product $product): bool
    {
        $threshold = max(1, (int) ($this->getSettings()['bestseller_threshold'] ?? 25));
        return (int) $product->get_total_sales() >= $threshold;
    }

    private function getDiscountPercent(\WC_Product $product): int
    {
        $regular = (float) $product->get_regular_price();
        $sale = (float) $product->get_sale_price();

        if ($regular <= 0 || $sale <= 0 || $sale >= $regular) {
            return 0;
        }

        return (int) round((($regular - $sale) / $regular) * 100);
    }
}
