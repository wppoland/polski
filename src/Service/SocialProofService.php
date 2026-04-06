<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Social proof notifications - real-time purchase popups.
 *
 * Shows floating toast notifications like "Jan from Warszawa just bought Product X"
 * using actual recent order data. Proven to increase conversion rates by 10-15%.
 *
 * Privacy-aware: uses first name only, city only, configurable anonymization.
 * Web Vitals: lazy-loaded via AJAX, no render-blocking, minimal DOM footprint.
 */
final class SocialProofService implements HasHooks
{
    private const OPTION = 'polski_social_proof';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('social_proof')) {
            return;
        }

        // Frontend display.
        add_action('wp_footer', [$this, 'renderContainer']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // AJAX endpoint for fetching recent orders.
        add_action('wp_ajax_polski_social_proof', [$this, 'ajaxGetNotifications']);
        add_action('wp_ajax_nopriv_polski_social_proof', [$this, 'ajaxGetNotifications']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return wp_parse_args(
            get_option(self::OPTION, []),
            [
                'display_interval' => 8,
                'display_duration' => 5,
                'max_notifications' => 10,
                'lookback_hours' => 48,
                'position' => 'bottom-left',
                'show_product_image' => true,
                'anonymize_name' => false,
                'hide_on_mobile' => false,
                'excluded_pages' => 'checkout,cart',
            ],
        );
    }

    public function enqueueAssets(): void
    {
        if ($this->shouldHide()) {
            return;
        }

        $settings = $this->getSettings();

        wp_enqueue_script(
            'polski-social-proof',
            plugins_url('assets/js/social-proof.js', \Polski\PLUGIN_FILE),
            ['jquery'],
            \Polski\VERSION,
            true,
        );

        wp_localize_script('polski-social-proof', 'polskiSocialProof', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('polski_social_proof'),
            'interval' => max(5, (int) $settings['display_interval']) * 1000,
            'duration' => max(3, (int) $settings['display_duration']) * 1000,
            'position' => $settings['position'],
        ]);

        wp_add_inline_style('polski-frontend', $this->getInlineCss($settings));
    }

    public function renderContainer(): void
    {
        if ($this->shouldHide()) {
            return;
        }

        echo '<div id="polski-social-proof-container" aria-live="polite" aria-atomic="true"></div>';
    }

    /**
     * AJAX handler: return recent purchase notifications.
     */
    public function ajaxGetNotifications(): void
    {
        check_ajax_referer('polski_social_proof', 'nonce');

        $settings = $this->getSettings();
        $maxNotifications = max(1, min(20, (int) $settings['max_notifications']));
        $lookbackHours = max(1, (int) $settings['lookback_hours']);
        $showImage = (bool) $settings['show_product_image'];
        $anonymize = (bool) $settings['anonymize_name'];

        $notifications = $this->getRecentPurchases($maxNotifications, $lookbackHours, $showImage, $anonymize);

        wp_send_json_success($notifications);
    }

    /**
     * @return list<array{name: string, city: string, product: string, image: string, time: string, url: string}>
     */
    private function getRecentPurchases(int $limit, int $lookbackHours, bool $showImage, bool $anonymize): array
    {
        $cacheKey = 'polski_social_proof_data';
        $cached = get_transient($cacheKey);

        if (is_array($cached)) {
            return array_values(array_slice($cached, 0, $limit));
        }

        $dateAfter = (new \DateTimeImmutable())->modify("-{$lookbackHours} hours")->format('Y-m-d H:i:s');

        $orders = wc_get_orders([
            'status' => ['completed', 'processing'],
            'limit' => $limit * 2,
            'date_created' => '>' . strtotime($dateAfter),
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        $orders = is_array($orders) ? $orders : [];

        $notifications = [];
        $seen = [];

        foreach ($orders as $order) {
            if (! $order instanceof \WC_Order) {
                continue;
            }

            $firstName = $order->get_billing_first_name();
            $city = $order->get_billing_city();

            if (empty($firstName) || empty($city)) {
                continue;
            }

            if ($anonymize) {
                $firstName = mb_substr($firstName, 0, 1) . '***';
            }

            $timeAgo = $this->humanTimeAgo($order->get_date_created()?->getTimestamp() ?? time());

            foreach ($order->get_items() as $item) {
                if (! $item instanceof \WC_Order_Item_Product) {
                    continue;
                }

                $product = $item->get_product();

                if (! $product instanceof \WC_Product) {
                    continue;
                }

                // Deduplicate by product.
                $productId = $product->get_id();

                if (isset($seen[$productId])) {
                    continue;
                }

                $seen[$productId] = true;

                $image = '';

                if ($showImage) {
                    $image = wp_get_attachment_image_url((int) $product->get_image_id(), 'woocommerce_gallery_thumbnail') ?: '';
                }

                $notifications[] = [
                    'name' => (string) $firstName,
                    'city' => (string) $city,
                    'product' => $product->get_name(),
                    'image' => $image,
                    'time' => $timeAgo,
                    'url' => $product->get_permalink(),
                ];

                if (count($notifications) >= $limit) {
                    break 2;
                }
            }
        }

        // Cache for 5 minutes.
        set_transient($cacheKey, $notifications, 300);

        return $notifications;
    }

    private function humanTimeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return __('just now', 'polski');
        }

        if ($diff < 3600) {
            $mins = (int) floor($diff / 60);

            return sprintf(
                /* translators: %d: number of minutes since purchase */
                _n('%d minute ago', '%d minutes ago', $mins, 'polski'),
                $mins,
            );
        }

        $hours = (int) floor($diff / 3600);

        return sprintf(
            /* translators: %d: number of hours since purchase */
            _n('%d hour ago', '%d hours ago', $hours, 'polski'),
            $hours,
        );
    }

    private function shouldHide(): bool
    {
        $settings = $this->getSettings();

        // Hide on mobile if configured.
        if ((bool) ($settings['hide_on_mobile'] ?? false) && wp_is_mobile()) {
            return true;
        }

        // Hide on excluded pages.
        $excluded = array_filter(array_map('trim', explode(',', (string) ($settings['excluded_pages'] ?? 'checkout,cart'))));

        if (in_array('checkout', $excluded, true) && is_checkout()) {
            return true;
        }

        if (in_array('cart', $excluded, true) && is_cart()) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function getInlineCss(array $settings): string
    {
        $position = $settings['position'] ?? 'bottom-left';
        $positionCss = match ($position) {
            'bottom-right' => 'bottom:20px;right:20px;',
            'top-left' => 'top:80px;left:20px;',
            'top-right' => 'top:80px;right:20px;',
            default => 'bottom:20px;left:20px;',
        };

        return "
            #polski-social-proof-container{position:fixed;{$positionCss}z-index:99998;max-width:340px;pointer-events:none}
            .polski-sp-toast{display:flex;align-items:center;gap:10px;padding:12px 16px;background:#fff;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.12);margin-top:8px;animation:polskiSpSlideIn .4s ease-out;pointer-events:auto;cursor:pointer;text-decoration:none;color:#1e293b;font-size:13px;line-height:1.4;border:1px solid #f1f5f9;transition:opacity .3s}
            .polski-sp-toast.hiding{opacity:0;transform:translateY(10px)}
            .polski-sp-toast img{width:48px;height:48px;border-radius:6px;object-fit:cover;flex-shrink:0}
            .polski-sp-toast strong{color:#0369a1}
            .polski-sp-toast .sp-time{font-size:11px;color:#94a3b8;margin-top:2px}
            .polski-sp-toast .sp-close{position:absolute;top:4px;right:8px;font-size:16px;color:#cbd5e1;cursor:pointer;line-height:1}
            @keyframes polskiSpSlideIn{from{opacity:0;transform:translateX(-20px)}to{opacity:1;transform:translateX(0)}}
            @media(max-width:480px){#polski-social-proof-container{left:10px;right:10px;max-width:none}}
        ";
    }
}
