<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Repository\CompareRepository;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

/**
 * Product comparison service for guests and logged-in customers.
 */
final class CompareService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_compare';
    private const GUEST_COOKIE = 'polski_compare_guest';
    private const ENDPOINT = 'polski-compare';

    public function __construct(
        private readonly CompareRepository $repository,
        private readonly TemplateLoader $templateLoader,
        private readonly PriceDisplayService $priceDisplay,
        private readonly DeliveryTimeService $deliveryTime,
        private readonly ProductInfoService $productInfo,
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

        add_action('init', [$this, 'registerEndpoint']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('woocommerce_archive_description', [$this, 'renderArchiveCompare'], 5);
        add_action('woocommerce_before_shop_loop', [$this, 'renderArchiveCompare'], 1);
        add_action('woocommerce_single_product_summary', [$this, 'renderSingleButton'], 34);
        add_action('woocommerce_after_shop_loop_item', [$this, 'renderLoopButton'], 20);
        add_action('wp_ajax_polski_compare_toggle', [$this, 'handleToggle']);
        add_action('wp_ajax_nopriv_polski_compare_toggle', [$this, 'handleToggle']);
        add_action('wp_ajax_polski_compare_clear', [$this, 'handleClear']);
        add_action('wp_ajax_nopriv_polski_compare_clear', [$this, 'handleClear']);
        add_filter('woocommerce_account_menu_items', [$this, 'addAccountMenuItem']);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [$this, 'renderAccountPage']);
        add_action('wp_login', [$this, 'transferGuestCompareToUser'], 10, 2);
        add_action('wp_footer', [$this, 'renderStickyBar'], 99);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('compare');
    }

    public function canUseCompare(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return (bool) ($this->getSettings()['allow_guests'] ?? true) || is_user_logged_in();
    }

    public function registerEndpoint(): void
    {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public function enqueueAssets(): void
    {
        if (! $this->isEnabled() || ! $this->shouldEnqueueAssets()) {
            return;
        }

        wp_enqueue_style(
            'polski-compare',
            \Polski\Plugin::instance()->url('assets/css/compare.css'),
            [],
            \Polski\VERSION,
        );

        wp_enqueue_script(
            'polski-compare',
            \Polski\Plugin::instance()->url('assets/js/compare.js'),
            [],
            \Polski\VERSION,
            ['in_footer' => true, 'strategy' => 'defer'],
        );

        wp_localize_script('polski-compare', 'polskiCompare', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('polski_compare'),
            'loginUrl' => wc_get_page_permalink('myaccount'),
            'compareUrl' => $this->getCompareUrl(),
            'allowGuests' => (bool) ($this->getSettings()['allow_guests'] ?? true),
            'showOnlyDifferences' => (bool) ($this->getSettings()['show_only_differences'] ?? false),
        ]);
    }

    public function renderSingleButton(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! ($this->getSettings()['show_on_single'] ?? true)) {
            return;
        }

        if (! $this->canUseCompare()) {
            return;
        }

        $this->templateLoader->include('single-product/compare-button', [
            'service' => $this,
            'product' => $product,
        ]);
    }

    public function renderLoopButton(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! ($this->getSettings()['show_on_loop'] ?? true)) {
            return;
        }

        if (! $this->canUseCompare()) {
            return;
        }

        $this->templateLoader->include('loop/compare-button', [
            'service' => $this,
            'product' => $product,
        ]);
    }

    public function handleToggle(): void
    {
        check_ajax_referer('polski_compare', 'nonce');

        if (! $this->canUseCompare()) {
            wp_send_json_error(['message' => $this->getLoginRequiredText()], 403);
        }

        $productId = isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0;
        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product) {
            wp_send_json_error(['message' => $this->getProductNotFoundText()], 404);
        }

        [$userId, $sessionId] = $this->getContext();

        if ($this->repository->exists($productId, $userId, $sessionId)) {
            $this->repository->remove($productId, $userId, $sessionId);

            wp_send_json_success([
                'in_compare' => false,
                'count' => $this->getCount(),
                'button_text' => $this->getAddText(),
                'compare_url' => $this->getCompareUrl(),
            ]);
        }

        $limit = $this->getMaxItems();

        $wasTrimmed = false;

        if ($this->repository->count($userId, $sessionId) >= $limit) {
            $this->repository->removeOldest($userId, $sessionId);
            $wasTrimmed = true;
        }

        $this->repository->add($productId, $userId, $sessionId);

        $response = [
            'in_compare' => true,
            'count' => $this->getCount(),
            'button_text' => $this->getRemoveText(),
            'compare_url' => $this->getCompareUrl(),
        ];

        if ($wasTrimmed) {
            $response['message'] = $this->getLimitNoticeText($limit);
        }

        wp_send_json_success($response);
    }

    public function handleClear(): void
    {
        check_ajax_referer('polski_compare', 'nonce');

        if (! $this->canUseCompare()) {
            wp_send_json_error(['message' => $this->getClearErrorText()], 403);
        }

        [$userId, $sessionId] = $this->getContext();
        $this->repository->clear($userId, $sessionId);

        wp_send_json_success([
            'count' => 0,
            'compare_url' => $this->getCompareUrl(),
        ]);
    }

    /**
     * @param array<string, string> $items
     * @return array<string, string>
     */
    public function addAccountMenuItem(array $items): array
    {
        if (! $this->isEnabled() || ! ($this->getSettings()['show_in_account'] ?? true)) {
            return $items;
        }

        $logout = $items['customer-logout'] ?? null;
        unset($items['customer-logout']);

        $items[self::ENDPOINT] = (string) ($this->getSettings()['account_label'] ?? __('Comparison', 'polski'));

        if ($logout !== null) {
            $items['customer-logout'] = $logout;
        }

        return $items;
    }

    public function renderAccountPage(): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in render method
        echo $this->renderCompareTable();
    }

    /**
     * Sticky bottom drawer with compared product thumbnails + a Compare button.
     * Hidden when the list is empty or the customer is already on the compare
     * page. Behaviour is opt-in via polski_compare.show_sticky_bar so existing
     * stores upgrade without an unexpected new UI element.
     */
    public function renderStickyBar(): void
    {
        if (! $this->isEnabled() || ! $this->canUseCompare()) {
            return;
        }

        if (function_exists('is_admin') && is_admin()) {
            return;
        }

        $settings = $this->getSettings();
        if (empty($settings['show_sticky_bar'])) {
            return;
        }

        $products = $this->getProducts();
        if ($products === []) {
            return;
        }

        $compareUrl = (string) (get_permalink((int) wc_get_page_id('compare')) ?: '');

        // Hide on the compare page itself.
        if ($compareUrl !== '' && is_page((int) wc_get_page_id('compare'))) {
            return;
        }

        echo '<div class="polski-compare-sticky" data-polski-compare-sticky aria-live="polite">';
        echo '<div class="polski-compare-sticky__items">';
        foreach (array_slice($products, 0, 4) as $product) {
            $image = $product->get_image('thumbnail', ['class' => 'polski-compare-sticky__thumb']);
            echo '<a class="polski-compare-sticky__item" href="' . esc_url($product->get_permalink()) . '" title="' . esc_attr($product->get_name()) . '">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC produces escaped <img>.
            echo $image;
            echo '</a>';
        }
        $extra = max(0, count($products) - 4);
        if ($extra > 0) {
            echo '<span class="polski-compare-sticky__more">+' . (int) $extra . '</span>';
        }
        echo '</div>';

        echo '<a class="polski-compare-sticky__cta button" href="' . esc_url($compareUrl) . '">';
        echo esc_html(sprintf(
            /* translators: %d: number of products in the compare list */
            _n('Porównaj (%d)', 'Porównaj (%d)', count($products), 'polski'),
            count($products),
        ));
        echo '</a>';

        echo '<button type="button" class="polski-compare-sticky__clear" data-polski-compare-clear-all aria-label="' . esc_attr__('Wyczyść porównanie', 'polski') . '">×</button>';

        echo '</div>';
    }

    public function renderArchiveCompare(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display toggle.
        $showCompare = isset($_GET['polski_compare']);
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if (! (is_shop() || is_post_type_archive('product')) || ! $showCompare) {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in render method
        echo $this->renderCompareTable();
    }

    public function transferGuestCompareToUser(string $userLogin, \WP_User $user): void
    {
        $guestSessionId = $this->getGuestSessionId();

        if ($guestSessionId === null || $user->ID <= 0) {
            return;
        }

        $this->repository->transferSessionToUser($guestSessionId, (int) $user->ID);
    }

    public function renderCompareTable(): string
    {
        $products = $this->getProducts();
        $rows = $this->buildRows($products);
        $differences = $this->calculateDifferences($rows);

        return $this->templateLoader->render('account/compare', [
            'service' => $this,
            'products' => $products,
            'rows' => $rows,
            'differences' => $differences,
            'title' => (string) ($this->getSettings()['title'] ?? ''),
            'intro_text' => (string) ($this->getSettings()['intro_text'] ?? ''),
            'empty_text' => (string) ($this->getSettings()['empty_text'] ?? ''),
            'show_only_differences' => (bool) ($this->getSettings()['show_only_differences'] ?? false),
            'highlight_differences' => (bool) ($this->getSettings()['highlight_differences'] ?? true),
            'feature_label' => $this->getFeatureLabel(),
            'differences_toggle_text' => $this->getDifferencesToggleText(),
            'show_product_image' => (bool) ($this->getSettings()['show_product_image'] ?? true),
            'show_add_to_cart' => (bool) ($this->getSettings()['show_add_to_cart'] ?? true),
            'show_remove_button' => (bool) ($this->getSettings()['show_remove_button'] ?? true),
        ]);
    }

    /**
     * @return list<\WC_Product>
     */
    public function getProducts(): array
    {
        [$userId, $sessionId] = $this->getContext(false);
        $items = $this->repository->findAll($userId, $sessionId);
        $products = [];

        foreach ($items as $item) {
            $product = wc_get_product($item->productId);

            if ($product instanceof \WC_Product) {
                $products[] = $product;
            }
        }

        return $products;
    }

    public function getCount(): int
    {
        return count($this->getProducts());
    }

    /**
     * @return array{product_id: int, in_compare: bool, label: string, count: int, compare_url: string}
     */
    public function getButtonData(\WC_Product $product): array
    {
        $inCompare = $this->isInCompare($product->get_id());

        return [
            'product_id' => $product->get_id(),
            'in_compare' => $inCompare,
            'label' => $inCompare ? $this->getRemoveText() : $this->getAddText(),
            'count' => $this->getCount(),
            'compare_url' => $this->getCompareUrl(),
        ];
    }

    public function isInCompare(int $productId): bool
    {
        [$userId, $sessionId] = $this->getContext(false);

        return $this->repository->exists($productId, $userId, $sessionId);
    }

    public function getAddText(): string
    {
        return (string) ($this->getSettings()['button_add_text'] ?? __('Add to compare', 'polski'));
    }

    public function getRemoveText(): string
    {
        return (string) ($this->getSettings()['button_remove_text'] ?? __('Remove from compare', 'polski'));
    }

    public function getClearText(): string
    {
        return (string) ($this->getSettings()['clear_text'] ?? __('Clear comparison', 'polski'));
    }

    public function getCompareLinkText(): string
    {
        return (string) ($this->getSettings()['compare_link_text'] ?? __('Compare products', 'polski'));
    }

    public function getLoginRequiredText(): string
    {
        return (string) ($this->getSettings()['login_required_text'] ?? __('Log in to use product comparison.', 'polski'));
    }

    public function getProductNotFoundText(): string
    {
        return (string) ($this->getSettings()['product_not_found_text'] ?? __('Product not found.', 'polski'));
    }

    public function getClearErrorText(): string
    {
        return (string) ($this->getSettings()['clear_error_text'] ?? __('You cannot clear the comparison.', 'polski'));
    }

    public function getFeatureLabel(): string
    {
        return (string) ($this->getSettings()['feature_label'] ?? __('Feature', 'polski'));
    }

    public function getDifferencesToggleText(): string
    {
        return (string) ($this->getSettings()['differences_toggle_text'] ?? __('Show only differences', 'polski'));
    }

    public function getLimitNoticeText(int $limit): string
    {
        $template = (string) ($this->getSettings()['limit_notice_text'] ?? __('You can compare up to {limit} products at once. The oldest entry was automatically replaced.', 'polski'));

        return str_replace('{limit}', (string) $limit, $template);
    }

    public function getCompareUrl(): string
    {
        if (is_user_logged_in() && ($this->getSettings()['show_in_account'] ?? true)) {
            return wc_get_account_endpoint_url(self::ENDPOINT);
        }

        return add_query_arg([
            'post_type' => 'product',
            'polski_compare' => '1',
        ], home_url('/'));
    }

    /**
     * @param list<\WC_Product> $products
     * @return list<array{key: string, label: string, values: array<int, string>, text_values: array<int, string>}>
     */
    public function buildRows(array $products): array
    {
        if ($products === []) {
            return [];
        }

        $rows = [];
        $rowMap = [
            'price' => (string) ($this->getSettings()['price_label'] ?? __('Price', 'polski')),
            'unit_price' => (string) ($this->getSettings()['unit_price_label'] ?? __('Unit price', 'polski')),
            'sku' => (string) ($this->getSettings()['sku_label'] ?? __('SKU', 'polski')),
            'availability' => (string) ($this->getSettings()['availability_label'] ?? __('Availability', 'polski')),
            'delivery_time' => (string) ($this->getSettings()['delivery_time_label'] ?? __('Delivery time', 'polski')),
            'brand' => (string) ($this->getSettings()['brand_label'] ?? __('Brand', 'polski')),
            'manufacturer' => (string) ($this->getSettings()['manufacturer_label'] ?? __('Manufacturer', 'polski')),
            'gtin' => (string) ($this->getSettings()['gtin_label'] ?? __('GTIN / EAN', 'polski')),
        ];

        if ((bool) ($this->getSettings()['show_description'] ?? true)) {
            $rowMap['description'] = (string) ($this->getSettings()['description_label'] ?? __('Short description', 'polski'));
        }

        foreach ($rowMap as $key => $label) {
            $values = [];
            $textValues = [];

            foreach ($products as $product) {
                [$html, $text] = $this->getFieldValue($product, $key);
                $values[] = $html;
                $textValues[] = $text;
            }

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'values' => $values,
                'text_values' => $textValues,
            ];
        }

        if ((bool) ($this->getSettings()['show_attributes'] ?? true)) {
            foreach ($this->getAttributeLabels($products) as $taxonomy => $label) {
                $values = [];
                $textValues = [];

                foreach ($products as $product) {
                    $value = $this->getAttributeValue($product, $taxonomy);
                    $values[] = esc_html($value);
                    $textValues[] = $value;
                }

                $rows[] = [
                    'key' => 'attribute_' . sanitize_key($taxonomy),
                    'label' => $label,
                    'values' => $values,
                    'text_values' => $textValues,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param list<array{key: string, label: string, values: array<int, string>, text_values: array<int, string>}> $rows
     * @return array<string, bool>
     */
    public function calculateDifferences(array $rows): array
    {
        $differences = [];

        foreach ($rows as $row) {
            $normalized = array_filter(array_map(
                static fn (string $value): string => trim(wp_strip_all_tags($value)),
                $row['text_values'],
            ));

            $differences[$row['key']] = count(array_unique($normalized)) > 1;
        }

        return $differences;
    }

    /**
     * Limit asset output to the contexts where compare UI can surface:
     * shop/taxonomy/single loops, the account comparison endpoint, and -
     * when the opt-in sticky bar is enabled - anywhere in the footer.
     */
    private function shouldEnqueueAssets(): bool
    {
        if (is_admin()) {
            return false;
        }

        if (is_shop() || is_product() || is_product_taxonomy() || is_account_page()) {
            return true;
        }

        return ! empty($this->getSettings()['show_sticky_bar']);
    }

    private function getMaxItems(): int
    {
        return max(2, min(6, (int) ($this->getSettings()['max_items'] ?? 4)));
    }

    /**
     * @return array{0: ?int, 1: ?string}
     */
    private function getContext(bool $createGuestSession = true): array
    {
        $userId = get_current_user_id() > 0 ? get_current_user_id() : null;
        $sessionId = $userId === null
            ? ($createGuestSession ? $this->getOrCreateGuestSessionId() : $this->getGuestSessionId())
            : null;

        return [$userId, $sessionId];
    }

    private function getGuestSessionId(): ?string
    {
        $cookie = sanitize_text_field((string) wp_unslash($_COOKIE[self::GUEST_COOKIE] ?? ''));

        return $cookie !== '' ? $cookie : null;
    }

    private function getOrCreateGuestSessionId(): string
    {
        $existing = $this->getGuestSessionId();

        if ($existing !== null) {
            return $existing;
        }

        $sessionId = wp_generate_uuid4();

        setcookie(
            self::GUEST_COOKIE,
            $sessionId,
            [
                'expires' => time() + MONTH_IN_SECONDS * 6,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN ?: '',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ],
        );

        $_COOKIE[self::GUEST_COOKIE] = $sessionId;

        return $sessionId;
    }

    /**
     * @param list<\WC_Product> $products
     * @return array<string, string>
     */
    private function getAttributeLabels(array $products): array
    {
        $labels = [];

        foreach ($products as $product) {
            foreach ($product->get_attributes() as $attribute) {
                if (! $attribute instanceof \WC_Product_Attribute) {
                    continue;
                }

                $name = $attribute->get_name();

                if (! isset($labels[$name])) {
                    $labels[$name] = wc_attribute_label($name, $product);
                }
            }
        }

        return $labels;
    }

    private function getAttributeValue(\WC_Product $product, string $attributeName): string
    {
        $attributes = $product->get_attributes();
        $attribute = $attributes[$attributeName] ?? null;

        if (! $attribute instanceof \WC_Product_Attribute) {
            return '-';
        }

        if ($attribute->is_taxonomy()) {
            $values = wc_get_product_terms($product->get_id(), $attributeName, ['fields' => 'names']);
            return $values !== [] ? implode(', ', $values) : '-';
        }

        $values = $attribute->get_options();

        return $values !== [] ? implode(', ', $values) : '-';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function getFieldValue(\WC_Product $product, string $key): array
    {
        switch ($key) {
            case 'price':
                $priceHtml = $product->get_price_html();
                return [$priceHtml !== '' ? $priceHtml : '-', $priceHtml !== '' ? wp_strip_all_tags($priceHtml) : '-'];

            case 'unit_price':
                $html = $this->priceDisplay->getUnitPriceHtml($product);
                return [$html !== '' ? $html : '-', $html !== '' ? wp_strip_all_tags($html) : '-'];

            case 'sku':
                $sku = $product->get_sku();
                return [esc_html($sku !== '' ? $sku : '-'), $sku !== '' ? $sku : '-'];

            case 'availability':
                $html = wc_get_stock_html($product);
                return [$html !== '' ? $html : '-', $html !== '' ? wp_strip_all_tags($html) : '-'];

            case 'delivery_time':
                $html = $this->deliveryTime->getDeliveryTimeHtml($product);
                return [$html !== '' ? $html : '-', $html !== '' ? wp_strip_all_tags($html) : '-'];

            case 'brand':
                $brands = $this->productInfo->getBrands($product);
                $text = $brands !== [] ? implode(', ', $brands) : '-';
                return [esc_html($text), $text];

            case 'manufacturer':
                $text = $this->productInfo->getManufacturer($product);
                $text = $text !== '' ? $text : '-';
                return [esc_html($text), $text];

            case 'gtin':
                $text = $this->productInfo->getGTIN($product);
                $text = $text !== '' ? $text : '-';
                return [esc_html($text), $text];

            case 'description':
                $text = wp_strip_all_tags((string) $product->get_short_description());
                $text = $text !== '' ? $text : '-';
                return [esc_html($text), $text];
        }

        return ['-', '-'];
    }
}
