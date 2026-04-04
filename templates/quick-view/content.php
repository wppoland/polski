<?php
/**
 * Quick view modal content.
 *
 * @var \Polski\Service\QuickViewService $service
 * @var \WC_Product                           $product
 * @var array<string, mixed>                  $settings
 * @var string                                $add_to_cart_html
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$images = $service->getProductImages($product);
$priceHtml = $service->getPriceHtml($product);
$unitPriceHtml = $service->getUnitPriceHtml($product);
$sku = $service->getSku($product);
$deliveryTimeHtml = $service->getDeliveryTimeHtml($product);
$brandHtml = $service->getBrandHtml($product);
$manufacturerHtml = $service->getManufacturerHtml($product);
$description = (bool) ($settings['show_short_description'] ?? true) ? wpautop(wp_kses_post($product->get_short_description())) : '';
?>
<div class="polski-quick-view-product product product-type-<?php echo esc_attr($product->get_type()); ?>">
    <div class="polski-quick-view-grid">
        <div class="polski-quick-view-media">
            <?php if ($images !== []) : ?>
                <div class="polski-quick-view-main-image">
                    <?php echo wp_get_attachment_image($images[0], 'large'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <?php if (count($images) > 1) : ?>
                    <div class="polski-quick-view-gallery">
                        <?php foreach ($images as $imageId) : ?>
                            <span class="polski-quick-view-thumb">
                                <?php echo wp_get_attachment_image($imageId, 'thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="polski-quick-view-main-image">
                    <?php echo $product->get_image('large'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="polski-quick-view-summary">
            <?php if ($service->shouldShowTitle()) : ?>
                <h2 class="product_title entry-title"><?php echo esc_html($product->get_name()); ?></h2>
            <?php endif; ?>

            <?php if ($sku !== '') : ?>
                <div class="polski-quick-view-meta">
                    <span class="polski-quick-view-meta__label"><?php echo esc_html((string) ($settings['sku_label'] ?? __('SKU', 'polski'))); ?>:</span>
                    <span><?php echo esc_html($sku); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($priceHtml !== '') : ?>
                <div class="polski-quick-view-price price"><?php echo wp_kses_post($priceHtml); ?></div>
            <?php endif; ?>

            <?php if ($unitPriceHtml !== '') : ?>
                <div class="polski-quick-view-unit-price"><?php echo wp_kses_post($unitPriceHtml); ?></div>
            <?php endif; ?>

            <?php if ($deliveryTimeHtml !== '') : ?>
                <div class="polski-quick-view-delivery"><?php echo wp_kses_post($deliveryTimeHtml); ?></div>
            <?php endif; ?>

            <?php if ($brandHtml !== '') : ?>
                <div class="polski-quick-view-brand"><?php echo wp_kses_post($brandHtml); ?></div>
            <?php endif; ?>

            <?php if ($manufacturerHtml !== '') : ?>
                <div class="polski-quick-view-manufacturer"><?php echo wp_kses_post($manufacturerHtml); ?></div>
            <?php endif; ?>

            <?php if ($description !== '') : ?>
                <div class="polski-quick-view-description"><?php echo wp_kses_post($description); ?></div>
            <?php endif; ?>

            <?php if ($add_to_cart_html !== '') : ?>
                <div class="polski-quick-view-cart">
                    <?php echo $add_to_cart_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>

            <?php if ((bool) ($settings['show_view_product_link'] ?? true)) : ?>
                <a
                    class="button alt polski-quick-view-link"
                    href="<?php echo esc_url(get_permalink($product->get_id()) ?: ''); ?>"
                    <?php echo (($settings['view_product_target'] ?? 'same_tab') === 'new_tab') ? 'target="_blank" rel="noopener noreferrer"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                >
                    <?php echo esc_html((string) ($settings['view_product_text'] ?? __('Zobacz pełną kartę produktu', 'polski'))); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
