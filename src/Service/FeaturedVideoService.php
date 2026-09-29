<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

/**
 * Product featured video rendering for PDP media area.
 */
final class FeaturedVideoService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_featured_video';

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
        // Not woocommerce_product_thumbnails: that runs inside the gallery
        // wrapper, where the slider viewport (overflow hidden) clipped the
        // video out of sight. Below the gallery and summary, before the tabs.
        add_action('woocommerce_after_single_product_summary', [$this, 'renderAfterGallery'], 5);
        add_action('woocommerce_before_single_product_summary', [$this, 'renderBeforeSummary'], 19);
    }

    public function enqueueAssets(): void
    {
        if (! ModulesPage::isModuleEnabled('featured_video') || ! is_product()) {
            return;
        }

        wp_enqueue_style(
            'polski-featured-video',
            \Polski\Plugin::instance()->url('assets/css/featured-video.css'),
            [],
            \Polski\VERSION,
        );
    }

    public function renderAfterGallery(): void
    {
        if (($this->getSettings()['position'] ?? 'after_gallery') !== 'after_gallery') {
            return;
        }

        $this->renderCurrentProductVideo();
    }

    public function renderBeforeSummary(): void
    {
        if (($this->getSettings()['position'] ?? 'after_gallery') !== 'before_summary') {
            return;
        }

        $this->renderCurrentProductVideo();
    }

    public function getVideoHtml(\WC_Product $product): string
    {
        $url = trim((string) $product->get_meta('_polski_featured_video_url', true));

        if ($url === '') {
            return '';
        }

        $autoplay = (bool) ($this->getSettings()['autoplay'] ?? false);

        if (preg_match('/\.(mp4|m4v|webm|ogv)(\?.*)?$/i', $url) === 1) {
            $shortcode = wp_video_shortcode([
                'src' => $url,
                'autoplay' => $autoplay ? 'on' : '',
                'preload' => 'metadata',
            ]);

            return is_string($shortcode) ? $shortcode : '';
        }

        if ($autoplay) {
            $url = add_query_arg('autoplay', '1', $url);
        }

        $embed = wp_oembed_get($url);

        return is_string($embed) ? $embed : '';
    }

    private function renderCurrentProductVideo(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! ModulesPage::isModuleEnabled('featured_video') || ! ($this->getSettings()['show_on_single'] ?? true)) {
            return;
        }

        $videoHtml = $this->getVideoHtml($product);

        if ($videoHtml === '') {
            return;
        }

        $title = trim((string) $product->get_meta('_polski_featured_video_title', true));

        if ($title === '') {
            $title = (string) ($this->getSettings()['title'] ?? __('Watch product in use', 'polski'));
        }

        $this->templateLoader->include('single-product/featured-video', [
            'video_html' => $videoHtml,
            'title' => $title,
            'intro_text' => (string) ($this->getSettings()['intro_text'] ?? ''),
            'show_title' => (bool) ($this->getSettings()['show_title'] ?? true),
            'show_intro' => (bool) ($this->getSettings()['show_intro'] ?? false),
            'product' => $product,
        ]);
    }
}
