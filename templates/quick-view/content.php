<?php
/**
 * Quick view modal content.
 *
 * @var \Polski\Service\QuickViewService $polski_service
 * @var \WC_Product                           $polski_product
 * @var array<string, mixed>                  $polski_settings
 * @var string                                $polski_add_to_cart_html
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$polski_images = $polski_service->getProductImages($polski_product);
$polski_priceHtml = $polski_service->getPriceHtml($polski_product);
$polski_unitPriceHtml = $polski_service->getUnitPriceHtml($polski_product);
$polski_omnibusPriceHtml = $polski_service->getOmnibusPriceHtml($polski_product);
$polski_sku = $polski_service->getSku($polski_product);
$polski_deliveryTimeHtml = $polski_service->getDeliveryTimeHtml($polski_product);
$polski_brandHtml = $polski_service->getBrandHtml($polski_product);
$polski_manufacturerHtml = $polski_service->getManufacturerHtml($polski_product);
$polski_gpsrHtml = $polski_service->getGpsrInfoHtml($polski_product);
$polski_description = (bool) ($polski_settings['show_short_description'] ?? true) ? wpautop(wp_kses_post($polski_product->get_short_description())) : '';
?>
<div class="polski-quick-view-product product product-type-<?php echo esc_attr($polski_product->get_type()); ?>">
    <div class="polski-quick-view-grid">
        <div class="polski-quick-view-media">
            <?php if ($polski_images !== []) : ?>
                <div class="polski-quick-view-main-image">
                    <?php echo wp_get_attachment_image($polski_images[0], 'large'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <?php if (count($polski_images) > 1) : ?>
                    <div class="polski-quick-view-gallery">
                        <?php foreach ($polski_images as $polski_imageId) : ?>
                            <span class="polski-quick-view-thumb">
                                <?php echo wp_get_attachment_image($polski_imageId, 'thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="polski-quick-view-main-image">
                    <?php echo $polski_product->get_image('large'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="polski-quick-view-summary">
            <?php if ($polski_service->shouldShowTitle()) : ?>
                <h2 class="product_title entry-title"><?php echo esc_html($polski_product->get_name()); ?></h2>
            <?php endif; ?>

            <?php if ($polski_sku !== '') : ?>
                <div class="polski-quick-view-meta">
                    <span class="polski-quick-view-meta__label"><?php echo esc_html((string) ($polski_settings['sku_label'] ?? __('SKU', 'polski'))); ?>:</span>
                    <span><?php echo esc_html($polski_sku); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($polski_priceHtml !== '') : ?>
                <div class="polski-quick-view-price price"><?php echo wp_kses_post($polski_priceHtml); ?></div>
            <?php endif; ?>

            <?php if ($polski_unitPriceHtml !== '') : ?>
                <div class="polski-quick-view-unit-price"><?php echo wp_kses_post($polski_unitPriceHtml); ?></div>
            <?php endif; ?>

            <?php if ($polski_omnibusPriceHtml !== '') : ?>
                <div class="polski-quick-view-omnibus"><?php echo wp_kses_post($polski_omnibusPriceHtml); ?></div>
            <?php endif; ?>

            <?php if ($polski_deliveryTimeHtml !== '') : ?>
                <div class="polski-quick-view-delivery"><?php echo wp_kses_post($polski_deliveryTimeHtml); ?></div>
            <?php endif; ?>

            <?php if ($polski_brandHtml !== '') : ?>
                <div class="polski-quick-view-brand"><?php echo wp_kses_post($polski_brandHtml); ?></div>
            <?php endif; ?>

            <?php if ($polski_manufacturerHtml !== '') : ?>
                <div class="polski-quick-view-manufacturer"><?php echo wp_kses_post($polski_manufacturerHtml); ?></div>
            <?php endif; ?>

            <?php if ($polski_gpsrHtml !== '') : ?>
                <div class="polski-quick-view-gpsr"><?php echo wp_kses_post($polski_gpsrHtml); ?></div>
            <?php endif; ?>

            <?php if ($polski_description !== '') : ?>
                <div class="polski-quick-view-description"><?php echo wp_kses_post($polski_description); ?></div>
            <?php endif; ?>

            <?php if ($polski_add_to_cart_html !== '') : ?>
                <div class="polski-quick-view-cart">
                    <?php echo $polski_add_to_cart_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>

            <?php if ((bool) ($polski_settings['show_view_product_link'] ?? true)) : ?>
                <a
                    class="button alt polski-quick-view-link"
                    href="<?php echo esc_url(get_permalink($polski_product->get_id()) ?: ''); ?>"
                    <?php echo (($polski_settings['view_product_target'] ?? 'same_tab') === 'new_tab') ? 'target="_blank" rel="noopener noreferrer"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                >
                    <?php echo esc_html((string) ($polski_settings['view_product_text'] ?? __('Zobacz pełną kartę produktu', 'polski'))); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
