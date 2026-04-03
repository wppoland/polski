<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

/**
 * Lightweight infinite scrolling for WooCommerce archives.
 */
final class InfiniteScrollService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_infinite_scroll';

    public function __construct(
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('woocommerce_after_shop_loop', [$this, 'renderControl'], 25);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('infinite_scroll');
    }

    public function enqueueAssets(): void
    {
        if (! $this->shouldRender()) {
            return;
        }

        wp_enqueue_style(
            'polski-infinite-scroll',
            \Polski\Plugin::instance()->url('assets/css/infinite-scroll.css'),
            [],
            \Polski\VERSION,
        );

        wp_enqueue_script(
            'polski-infinite-scroll',
            \Polski\Plugin::instance()->url('assets/js/infinite-scroll.js'),
            [],
            \Polski\VERSION,
            true,
        );

        wp_localize_script('polski-infinite-scroll', 'polskiInfiniteScroll', [
            'mode' => (string) ($this->getSettings()['mode'] ?? 'button'),
            'loadingText' => (string) ($this->getSettings()['loading_text'] ?? ''),
            'errorText' => (string) ($this->getSettings()['error_text'] ?? ''),
            'endText' => (string) ($this->getSettings()['end_text'] ?? ''),
            'showStatus' => (bool) ($this->getSettings()['show_status'] ?? true),
            'showButtonInAutoMode' => (bool) ($this->getSettings()['show_button_in_auto_mode'] ?? false),
            'autoAfterPages' => max(0, (int) ($this->getSettings()['auto_after_pages'] ?? 0)),
        ]);
    }

    public function renderControl(): void
    {
        if (! $this->shouldRender()) {
            return;
        }

        global $wp_query;

        if (! $wp_query instanceof \WP_Query || (int) $wp_query->max_num_pages <= 1) {
            return;
        }

        $nextPage = get_next_posts_page_link((int) $wp_query->max_num_pages);

        if (! is_string($nextPage) || $nextPage === '') {
            return;
        }

        $this->templateLoader->include('archive/infinite-scroll', [
            'service' => $this,
            'next_page_url' => $nextPage,
            'settings' => $this->getSettings(),
        ]);
    }

    private function shouldRender(): bool
    {
        if (! $this->isEnabled() || is_admin()) {
            return false;
        }

        if (is_shop() && ($this->getSettings()['show_on_shop'] ?? true)) {
            return true;
        }

        if ((is_product_category() || is_product_tag() || is_product_taxonomy()) && ($this->getSettings()['show_on_taxonomies'] ?? true)) {
            return true;
        }

        return false;
    }
}
