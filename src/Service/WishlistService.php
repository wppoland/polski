<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Repository\WishlistRepository;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

/**
 * Wishlist with guest and customer support.
 */
final class WishlistService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_wishlist';
    private const GUEST_COOKIE = 'polski_wishlist_guest';
    private const ENDPOINT = 'polski-wishlist';

    public function __construct(
        private readonly WishlistRepository $repository,
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerEndpoint']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('woocommerce_single_product_summary', [$this, 'renderSingleButton'], 33);
        add_action('woocommerce_after_shop_loop_item', [$this, 'renderLoopButton'], 19);
        add_action('wp_ajax_polski_wishlist_toggle', [$this, 'handleToggle']);
        add_action('wp_ajax_nopriv_polski_wishlist_toggle', [$this, 'handleToggle']);
        add_filter('woocommerce_account_menu_items', [$this, 'addAccountMenuItem']);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [$this, 'renderAccountPage']);
        add_action('wp_login', [$this, 'transferGuestWishlistToUser'], 10, 2);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('wishlist');
    }

    public function registerEndpoint(): void
    {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public function enqueueAssets(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        wp_enqueue_style(
            'polski-wishlist',
            \Polski\Plugin::instance()->url('assets/css/wishlist.css'),
            [],
            \Polski\VERSION,
        );

        wp_enqueue_script(
            'polski-wishlist',
            \Polski\Plugin::instance()->url('assets/js/wishlist.js'),
            [],
            \Polski\VERSION,
            true,
        );

        wp_localize_script('polski-wishlist', 'polskiWishlist', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('polski_wishlist'),
            'loginUrl' => wc_get_page_permalink('myaccount'),
            'allowGuests' => (bool) ($this->getSettings()['allow_guests'] ?? true),
        ]);
    }

    public function renderSingleButton(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! ($this->getSettings()['show_on_single'] ?? true)) {
            return;
        }

        if (! $this->canUseWishlist()) {
            return;
        }

        $this->templateLoader->include('single-product/wishlist-button', [
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

        if (! $this->canUseWishlist()) {
            return;
        }

        $this->templateLoader->include('loop/wishlist-button', [
            'service' => $this,
            'product' => $product,
        ]);
    }

    public function handleToggle(): void
    {
        check_ajax_referer('polski_wishlist', 'nonce');

        if (! $this->canUseWishlist()) {
            wp_send_json_error(['message' => $this->getLoginRequiredText()], 403);
        }

        $productId = (int) wp_unslash($_POST['product_id'] ?? 0);
        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product) {
            wp_send_json_error(['message' => $this->getProductNotFoundText()], 404);
        }

        $userId = get_current_user_id() > 0 ? get_current_user_id() : null;
        $sessionId = $userId === null ? $this->getOrCreateGuestSessionId() : null;

        if ($this->repository->exists($productId, $userId, $sessionId)) {
            $this->repository->remove($productId, $userId, $sessionId);

            wp_send_json_success([
                'in_wishlist' => false,
                'count' => $this->getCount(),
                'button_text' => $this->getAddText(),
            ]);
        }

        $this->repository->add($productId, $userId, $sessionId);

        wp_send_json_success([
            'in_wishlist' => true,
            'count' => $this->getCount(),
            'button_text' => $this->getRemoveText(),
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

        $items[self::ENDPOINT] = (string) ($this->getSettings()['account_label'] ?? __('Ulubione', 'polski'));

        if ($logout !== null) {
            $items['customer-logout'] = $logout;
        }

        return $items;
    }

    public function renderAccountPage(): void
    {
        echo $this->renderWishlist();
    }

    public function transferGuestWishlistToUser(string $userLogin, \WP_User $user): void
    {
        $guestSessionId = $this->getGuestSessionId();

        if ($guestSessionId === null || $user->ID <= 0) {
            return;
        }

        $this->repository->transferSessionToUser($guestSessionId, (int) $user->ID);
    }

    public function renderWishlist(): string
    {
        $products = $this->getProducts();

        return $this->templateLoader->render('account/wishlist', [
            'service' => $this,
            'products' => $products,
            'title' => (string) ($this->getSettings()['title'] ?? ''),
            'intro_text' => (string) ($this->getSettings()['account_intro_text'] ?? ''),
            'empty_text' => (string) ($this->getSettings()['empty_text'] ?? ''),
            'show_title' => (bool) ($this->getSettings()['show_title'] ?? true),
            'show_product_image' => (bool) ($this->getSettings()['show_product_image'] ?? true),
            'show_product_name' => (bool) ($this->getSettings()['show_product_name'] ?? true),
            'show_price' => (bool) ($this->getSettings()['show_price'] ?? true),
            'show_add_to_cart' => (bool) ($this->getSettings()['show_add_to_cart'] ?? true),
            'show_remove_button' => (bool) ($this->getSettings()['show_remove_button'] ?? true),
            'grid_columns' => $this->getGridColumns(),
        ]);
    }

    public function isInWishlist(int $productId): bool
    {
        $userId = get_current_user_id() > 0 ? get_current_user_id() : null;
        $sessionId = $userId === null ? $this->getGuestSessionId() : null;

        return $this->repository->exists($productId, $userId, $sessionId);
    }

    /**
     * @return list<\WC_Product>
     */
    public function getProducts(): array
    {
        $userId = get_current_user_id() > 0 ? get_current_user_id() : null;
        $sessionId = $userId === null ? $this->getGuestSessionId() : null;
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

    public function getButtonData(\WC_Product $product): array
    {
        $inWishlist = $this->isInWishlist($product->get_id());

        return [
            'product_id' => $product->get_id(),
            'in_wishlist' => $inWishlist,
            'label' => $inWishlist ? $this->getRemoveText() : $this->getAddText(),
        ];
    }

    public function getAddText(): string
    {
        return (string) ($this->getSettings()['button_add_text'] ?? __('Dodaj do ulubionych', 'polski'));
    }

    public function getRemoveText(): string
    {
        return (string) ($this->getSettings()['button_remove_text'] ?? __('Usuń z ulubionych', 'polski'));
    }

    public function getLoginRequiredText(): string
    {
        return (string) ($this->getSettings()['login_required_text'] ?? __('Zaloguj się, aby korzystać z listy życzeń.', 'polski'));
    }

    public function getProductNotFoundText(): string
    {
        return (string) ($this->getSettings()['product_not_found_text'] ?? __('Nie znaleziono produktu.', 'polski'));
    }

    public function canUseWishlist(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return (bool) ($this->getSettings()['allow_guests'] ?? true) || is_user_logged_in();
    }

    private function getGridColumns(): int
    {
        return max(2, min(6, (int) ($this->getSettings()['grid_columns'] ?? 4)));
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
                'path' => COOKIEPATH ?: '/',
                'domain' => COOKIE_DOMAIN ?: '',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ],
        );

        $_COOKIE[self::GUEST_COOKIE] = $sessionId;

        return $sessionId;
    }
}
