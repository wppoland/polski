<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Live Cart Sidebar - slide-in drawer on add-to-cart.
 *
 * Opens a right-side drawer showing cart contents whenever a product
 * is added to cart (AJAX or page reload). Uses WooCommerce cart fragments
 * for real-time updates. Pure CSS animations, no JS framework dependency.
 */
final class LiveCartService implements HasHooks
{
    private const OPTION = 'polski_live_cart';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('live_cart')) {
            return;
        }

        add_action('wp_footer', [$this, 'renderDrawer']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('woocommerce_add_to_cart_fragments', [$this, 'cartFragments']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return wp_parse_args(
            get_option(self::OPTION, []),
            [
                'auto_open' => true,
                'show_subtotal' => true,
                'show_shipping_notice' => true,
                'free_shipping_threshold' => 0,
                'position' => 'right',
                'overlay' => true,
            ],
        );
    }

    public function enqueueAssets(): void
    {
        if (is_checkout() || is_cart()) {
            return;
        }

        wp_enqueue_style(
            'polski-live-cart',
            plugins_url('assets/css/live-cart.css', POLSKI_FILE),
            [],
            POLSKI_VERSION,
        );

        wp_enqueue_script(
            'polski-live-cart',
            plugins_url('assets/js/live-cart.js', POLSKI_FILE),
            ['jquery'],
            POLSKI_VERSION,
            true,
        );

        $settings = $this->getSettings();

        wp_localize_script('polski-live-cart', 'polskiLiveCart', [
            'autoOpen' => (bool) $settings['auto_open'],
            'cartUrl' => wc_get_cart_url(),
            'checkoutUrl' => wc_get_checkout_url(),
            'i18n' => [
                'cart' => __('Cart', 'polski'),
                'close' => __('Close', 'polski'),
                'viewCart' => __('View cart', 'polski'),
                'checkout' => __('Checkout', 'polski'),
                'emptyCart' => __('Your cart is empty', 'polski'),
                'subtotal' => __('Subtotal', 'polski'),
                'freeShipping' => __('Free shipping from', 'polski'),
                'freeShippingReached' => __('You qualify for free shipping!', 'polski'),
            ],
            'showSubtotal' => (bool) $settings['show_subtotal'],
            'showShippingNotice' => (bool) $settings['show_shipping_notice'],
            'freeShippingThreshold' => (float) $settings['free_shipping_threshold'],
            'position' => $settings['position'],
            'overlay' => (bool) $settings['overlay'],
        ]);
    }

    /**
     * @param array<string, string> $fragments
     * @return array<string, string>
     */
    public function cartFragments(array $fragments): array
    {
        ob_start();
        $this->renderCartItems();
        $fragments['.polski-cart-drawer__items'] = ob_get_clean();

        ob_start();
        $this->renderCartFooter();
        $fragments['.polski-cart-drawer__footer'] = ob_get_clean();

        ob_start();
        $this->renderCartCount();
        $fragments['.polski-cart-drawer__count'] = ob_get_clean();

        return $fragments;
    }

    public function renderDrawer(): void
    {
        if (is_checkout() || is_cart()) {
            return;
        }

        $settings = $this->getSettings();
        $position = $settings['position'] === 'left' ? 'left' : 'right';

        echo '<div class="polski-cart-drawer" data-position="' . esc_attr($position) . '" aria-hidden="true" role="dialog" aria-label="' . esc_attr__('Cart', 'polski') . '">';

        // Overlay.
        if ($settings['overlay']) {
            echo '<div class="polski-cart-drawer__overlay"></div>';
        }

        echo '<div class="polski-cart-drawer__panel">';

        // Header.
        echo '<div class="polski-cart-drawer__header">';
        echo '<h3 class="polski-cart-drawer__title">' . esc_html__('Cart', 'polski') . ' ';
        $this->renderCartCount();
        echo '</h3>';
        echo '<button type="button" class="polski-cart-drawer__close" aria-label="' . esc_attr__('Close', 'polski') . '">';
        echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>';
        echo '</button>';
        echo '</div>';

        // Items.
        $this->renderCartItems();

        // Footer.
        $this->renderCartFooter();

        echo '</div>'; // .panel
        echo '</div>'; // .drawer
    }

    private function renderCartCount(): void
    {
        $count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
        printf(
            '<span class="polski-cart-drawer__count">(%d)</span>',
            $count,
        );
    }

    private function renderCartItems(): void
    {
        echo '<div class="polski-cart-drawer__items">';

        $cart = WC()->cart;

        if (! $cart || $cart->is_empty()) {
            echo '<p class="polski-cart-drawer__empty">' . esc_html__('Your cart is empty', 'polski') . '</p>';
            echo '</div>';
            return;
        }

        foreach ($cart->get_cart() as $cartItemKey => $cartItem) {
            /** @var \WC_Product $product */
            $product = $cartItem['data'];
            $quantity = $cartItem['quantity'];
            $thumbnail = $product->get_image([60, 60]);
            $name = $product->get_name();
            $price = WC()->cart->get_product_price($product);
            $permalink = $product->get_permalink($cartItem);

            echo '<div class="polski-cart-drawer__item" data-key="' . esc_attr($cartItemKey) . '">';
            echo '<div class="polski-cart-drawer__item-image">';
            echo '<a href="' . esc_url($permalink) . '">' . $thumbnail . '</a>';
            echo '</div>';
            echo '<div class="polski-cart-drawer__item-details">';
            echo '<a href="' . esc_url($permalink) . '" class="polski-cart-drawer__item-name">' . esc_html($name) . '</a>';
            echo '<div class="polski-cart-drawer__item-meta">';
            echo '<span class="polski-cart-drawer__item-qty">' . esc_html((string) $quantity) . ' &times;</span> ';
            echo '<span class="polski-cart-drawer__item-price">' . $price . '</span>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    private function renderCartFooter(): void
    {
        $settings = $this->getSettings();
        $cart = WC()->cart;
        $subtotal = $cart ? $cart->get_cart_subtotal() : '';

        echo '<div class="polski-cart-drawer__footer">';

        // Free shipping progress.
        if ($settings['show_shipping_notice'] && $settings['free_shipping_threshold'] > 0 && $cart) {
            $total = (float) $cart->get_subtotal();
            $threshold = (float) $settings['free_shipping_threshold'];

            if ($total >= $threshold) {
                echo '<div class="polski-cart-drawer__shipping polski-cart-drawer__shipping--reached">';
                echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> ';
                echo esc_html__('You qualify for free shipping!', 'polski');
                echo '</div>';
            } else {
                $remaining = $threshold - $total;
                echo '<div class="polski-cart-drawer__shipping">';
                $progress = min(100, ($total / $threshold) * 100);
                echo '<div class="polski-cart-drawer__shipping-bar"><div class="polski-cart-drawer__shipping-fill" style="width:' . esc_attr((string) round($progress)) . '%"></div></div>';
                printf(
                    '<span>' . esc_html__('Add %s for free shipping', 'polski') . '</span>',
                    wc_price($remaining),
                );
                echo '</div>';
            }
        }

        // Subtotal.
        if ($settings['show_subtotal'] && $cart && ! $cart->is_empty()) {
            echo '<div class="polski-cart-drawer__subtotal">';
            echo '<span>' . esc_html__('Subtotal', 'polski') . '</span>';
            echo '<strong>' . $subtotal . '</strong>';
            echo '</div>';
        }

        // Action buttons.
        if ($cart && ! $cart->is_empty()) {
            echo '<div class="polski-cart-drawer__actions">';
            echo '<a href="' . esc_url(wc_get_cart_url()) . '" class="polski-cart-drawer__btn polski-cart-drawer__btn--secondary">' . esc_html__('View cart', 'polski') . '</a>';
            echo '<a href="' . esc_url(wc_get_checkout_url()) . '" class="polski-cart-drawer__btn polski-cart-drawer__btn--primary">' . esc_html__('Checkout', 'polski') . '</a>';
            echo '</div>';
        }

        echo '</div>';
    }
}
