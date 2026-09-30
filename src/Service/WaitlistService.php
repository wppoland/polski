<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Repository\WaitlistRepository;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;
use WPPoland\StorefrontKit\Waitlist\WaitlistEngine;

/**
 * Waitlist signups and back-in-stock notifications.
 */
final class WaitlistService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_waitlist';

    private readonly WaitlistEngine $engine;

    public function __construct(
        private readonly WaitlistRepository $repository,
        private readonly TemplateLoader $templateLoader,
    ) {
        $this->engine = new WaitlistEngine(
            repository: $this->repository,
            ajaxAction: 'polski_waitlist_subscribe',
            nonceAction: 'polski_waitlist',
            scriptObjectName: 'polskiWaitlist',
            assetHandle: 'polski-waitlist',
            styleUrl: \Polski\Plugin::instance()->url('assets/css/waitlist.css'),
            scriptUrl: \Polski\Plugin::instance()->url('assets/js/waitlist.js'),
            version: \Polski\VERSION,
            templateName: 'single-product/waitlist-form',
            defaultMessages: [
                'generic_error' => __('Something went wrong. Please try again.', 'polski'),
                'product_not_found' => __('Product not found.', 'polski'),
                'disabled' => __('Waitlist is unavailable for this product.', 'polski'),
                'variation_required' => __('Waitlist is unavailable for this product.', 'polski'),
                'invalid_email' => __('Provide a valid email address.', 'polski'),
                'privacy_error' => __('You must accept the consent for email contact.', 'polski'),
                'login_required' => __('Login to join the waitlist.', 'polski'),
                'success' => __('Thank you. You have been added to the waitlist.', 'polski'),
                'notify_subject' => __('Product back in stock - {product_name}', 'polski'),
                'notify_intro' => __('Product {product_name} is back in stock.', 'polski'),
                'notify_outro' => __('If you no longer wish to receive these messages, simply ignore this email.', 'polski'),
            ],
            isEnabled: fn (): bool => $this->isEnabled(),
            settings: fn (): array => $this->getSettings(),
            renderTemplate: function (string $template, array $data): void {
                // Variable products get their own hidden form from
                // renderVariableForm(), which signs up for one variation.
                $product = $data['product'] ?? null;
                if ($product instanceof \WC_Product && $product->is_type('variable')) {
                    return;
                }

                $this->templateLoader->include($template, $data);
            },
        );
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        $this->engine->registerHooks();

        add_action('woocommerce_single_product_summary', [$this, 'renderVariableForm'], 32);
        add_filter('woocommerce_available_variation', [$this, 'flagVariation'], 10, 3);
        // WooCommerce fires this instead of woocommerce_product_set_stock_status
        // for variations, on every save path (admin, REST, order restock).
        add_action('woocommerce_variation_set_stock_status', [$this, 'notifyVariation'], 10, 3);
        add_action('woocommerce_product_set_stock_status', [$this, 'notifyParentManagedVariations'], 10, 3);
    }

    /**
     * Hidden form on variable products; waitlist.js shows it and sets the
     * variation id once an out-of-stock variation is selected.
     */
    public function renderVariableForm(): void
    {
        global $product;

        if (! $this->isEnabled() || ! $product instanceof \WC_Product_Variable || ! ($this->getSettings()['show_on_single'] ?? true)) {
            return;
        }

        $this->templateLoader->include('single-product/waitlist-form', [
            'product' => $product,
            'settings' => $this->getSettings(),
            'email' => is_user_logged_in() ? wp_get_current_user()->user_email : '',
        ]);
    }

    /**
     * Same eligibility rule the engine applies to a simple product.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function flagVariation(array $data, \WC_Product $parent, \WC_Product_Variation $variation): array
    {
        if ($this->isEnabled()) {
            $data['polski_waitlist'] = ! $variation->is_in_stock() || $variation->get_stock_status() === 'onbackorder';
        }

        return $data;
    }

    /**
     * Variations whose stock the parent manages are synced by a direct meta
     * write, so they fire no hook of their own.
     */
    public function notifyParentManagedVariations(int $productId, string $stockStatus, \WC_Product $product): void
    {
        if ($stockStatus !== 'instock' || ! $product->is_type('variable') || ! $product->get_manage_stock()) {
            return;
        }

        foreach ($product->get_children() as $childId) {
            $child = wc_get_product($childId);
            if ($child instanceof \WC_Product_Variation && $child->get_manage_stock() === 'parent') {
                $this->notifyVariation($childId, $stockStatus, $child);
            }
        }
    }

    public function notifyVariation(int $variationId, string $stockStatus, \WC_Product $variation): void
    {
        // The engine links to get_permalink($id), which for a variation is
        // not a public URL; point it at the product page with the variation
        // preselected instead.
        $link = static fn (string $url, \WP_Post $post): string => $post->ID === $variationId ? $variation->get_permalink() : $url;

        add_filter('post_type_link', $link, 10, 2);
        $this->engine->notifySubscribers($variationId, $stockStatus, $variation);
        remove_filter('post_type_link', $link, 10);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('waitlist');
    }
}
