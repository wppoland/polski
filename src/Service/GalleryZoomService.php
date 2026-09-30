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
 * Lightweight gallery zoom and lightbox enhancement.
 */
final class GalleryZoomService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_gallery_zoom';

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
        add_action('wp_footer', [$this, 'renderLightboxShell']);
        add_action('wp', [$this, 'replaceThemeGalleryFeatures']);
    }

    /**
     * WooCommerce's own hover zoom stacked with ours and its zoom layer sat on
     * top of the image, so a mouse click never reached the lightbox. While our
     * zoom is on, the theme's zoom is switched off. WooCommerce's lightbox is
     * kept: it shows the full-size file and browses the gallery.
     */
    public function replaceThemeGalleryFeatures(): void
    {
        if (! ModulesPage::isModuleEnabled('gallery_zoom') || ! is_product()) {
            return;
        }

        if ($this->getSettings()['enable_zoom'] ?? true) {
            remove_theme_support('wc-product-gallery-zoom');
        }
    }

    /**
     * Our lightbox is only a fallback for themes without WooCommerce's own.
     */
    private function ownLightbox(): bool
    {
        return (bool) ($this->getSettings()['enable_lightbox'] ?? true)
            && ! current_theme_supports('wc-product-gallery-lightbox');
    }

    public function enqueueAssets(): void
    {
        if (! ModulesPage::isModuleEnabled('gallery_zoom') || ! is_product()) {
            return;
        }

        wp_enqueue_style(
            'polski-gallery-zoom',
            \Polski\Plugin::instance()->url('assets/css/gallery-zoom.css'),
            [],
            \Polski\VERSION,
        );

        wp_enqueue_script(
            'polski-gallery-zoom',
            \Polski\Plugin::instance()->url('assets/js/gallery-zoom.js'),
            [],
            \Polski\VERSION,
            ['in_footer' => true, 'strategy' => 'defer'],
        );

        wp_localize_script('polski-gallery-zoom', 'polskiGalleryZoom', [
            'zoomScale' => (float) ($this->getSettings()['zoom_scale'] ?? 1.45),
            'enableZoom' => (bool) ($this->getSettings()['enable_zoom'] ?? true),
            'enableLightbox' => $this->ownLightbox(),
            'showBackdropClose' => (bool) ($this->getSettings()['show_backdrop_close'] ?? true),
            'triggerLabel' => (string) ($this->getSettings()['trigger_label'] ?? __('Zoom in on the product photo', 'polski')),
        ]);
    }

    public function renderLightboxShell(): void
    {
        if (! ModulesPage::isModuleEnabled('gallery_zoom') || ! is_product() || ! $this->ownLightbox()) {
            return;
        }

        $this->templateLoader->include('shared/gallery-lightbox', [
            'settings' => $this->getSettings(),
        ]);
    }
}
