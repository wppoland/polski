<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Repository\WaitlistRepository;
use Polski\Util\Formatter;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

/**
 * Waitlist signups and back-in-stock notifications.
 */
final class WaitlistService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_waitlist';

    public function __construct(
        private readonly WaitlistRepository $repository,
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_action('woocommerce_single_product_summary', [$this, 'renderForm'], 32);
        add_action('wp_ajax_polski_waitlist_subscribe', [$this, 'handleSubscribe']);
        add_action('wp_ajax_nopriv_polski_waitlist_subscribe', [$this, 'handleSubscribe']);
        add_action('woocommerce_product_set_stock_status', [$this, 'notifySubscribers'], 10, 3);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('waitlist');
    }

    public function enqueueAssets(): void
    {
        if (! $this->isEnabled() || ! is_product()) {
            return;
        }

        wp_enqueue_style(
            'polski-waitlist',
            \Polski\Plugin::instance()->url('assets/css/waitlist.css'),
            [],
            \Polski\VERSION,
        );

        wp_enqueue_script(
            'polski-waitlist',
            \Polski\Plugin::instance()->url('assets/js/waitlist.js'),
            [],
            \Polski\VERSION,
            true,
        );

        wp_localize_script('polski-waitlist', 'polskiWaitlist', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('polski_waitlist'),
        ]);
    }

    public function renderForm(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! $this->shouldRenderForProduct($product)) {
            return;
        }

        $this->templateLoader->include('single-product/waitlist-form', [
            'product' => $product,
            'settings' => $this->getSettings(),
            'email' => is_user_logged_in() ? wp_get_current_user()->user_email : '',
        ]);
    }

    public function handleSubscribe(): void
    {
        check_ajax_referer('polski_waitlist', 'nonce');

        $productId = isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0;
        $email = isset($_POST['email']) ? sanitize_email((string) wp_unslash($_POST['email'])) : '';
        $privacy = isset($_POST['privacy']) ? sanitize_text_field((string) wp_unslash($_POST['privacy'])) : '';
        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product) {
            wp_send_json_error(['message' => (string) ($this->getSettings()['product_not_found_text'] ?? __('Product not found.', 'polski'))], 404);
        }

        if (! $this->shouldRenderForProduct($product)) {
            wp_send_json_error(['message' => (string) ($this->getSettings()['disabled_text'] ?? __('Waitlist is unavailable for this product.', 'polski'))], 400);
        }

        if ($email === '' || ! is_email($email)) {
            wp_send_json_error(['message' => (string) ($this->getSettings()['invalid_email_text'] ?? __('Provide a valid email address.', 'polski'))], 422);
        }

        if ($privacy !== '1') {
            wp_send_json_error(['message' => (string) ($this->getSettings()['privacy_error_text'] ?? __('You must accept the consent for email contact.', 'polski'))], 422);
        }

        if (! ($this->getSettings()['allow_guests'] ?? true) && ! is_user_logged_in()) {
            wp_send_json_error(['message' => (string) ($this->getSettings()['login_required_text'] ?? __('Login to join the waitlist.', 'polski'))], 403);
        }

        $this->repository->subscribe($productId, $email, get_current_user_id() ?: null);

        wp_send_json_success([
            'message' => (string) ($this->getSettings()['success_text'] ?? __('Thank you. You have been added to the waitlist.', 'polski')),
        ]);
    }

    public function notifySubscribers(int $productId, string $stockStatus, \WC_Product $product): void
    {
        if (! $this->isEnabled() || $stockStatus !== 'instock') {
            return;
        }

        foreach ($this->repository->findPendingByProduct($productId) as $subscription) {
            $subject = Formatter::interpolate(
                (string) ($this->getSettings()['notify_subject'] ?? __('Product back in stock - {product_name}', 'polski')),
                ['product_name' => $product->get_name()],
            );

            $message = sprintf(
                "%s\n\n%s\n%s",
                str_replace('{product_name}', $product->get_name(), (string) ($this->getSettings()['notify_intro_text'] ?? __('Product {product_name} is back in stock.', 'polski'))),
                get_permalink($productId),
                (string) ($this->getSettings()['notify_outro_text'] ?? __('If you no longer wish to receive these messages, simply ignore this email.', 'polski')),
            );

            if (wp_mail($subscription->email, $subject, $message)) {
                $this->repository->markNotified($subscription->id);
            }
        }
    }

    private function shouldRenderForProduct(\WC_Product $product): bool
    {
        if (! $this->isEnabled() || ! ($this->getSettings()['show_on_single'] ?? true)) {
            return false;
        }

        return ! $product->is_in_stock() || $product->get_stock_status() === 'onbackorder';
    }
}
