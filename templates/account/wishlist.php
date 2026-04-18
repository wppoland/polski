<?php
/**
 * Wishlist account view.
 *
 * @var \Polski\Service\WishlistService $polski_service
 * @var list<WC_Product>                     $polski_products
 * @var string                               $polski_title
 * @var string                               $polski_intro_text
 * @var string                               $polski_empty_text
 * @var bool                                 $polski_show_title
 * @var bool                                 $polski_show_product_image
 * @var bool                                 $polski_show_product_name
 * @var bool                                 $polski_show_price
 * @var bool                                 $polski_show_add_to_cart
 * @var bool                                 $polski_show_remove_button
 * @var int                                  $polski_grid_columns
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<div class="polski-wishlist-account">
    <?php if ($polski_show_title) : ?>
        <h2><?php echo esc_html($polski_title); ?></h2>
    <?php endif; ?>

    <?php if ($polski_intro_text !== '') : ?>
        <div class="polski-wishlist-account__intro">
            <?php echo wp_kses_post(wpautop(wp_kses_post($polski_intro_text))); ?>
        </div>
    <?php endif; ?>

    <?php if ($polski_products === []) : ?>
        <p><?php echo esc_html($polski_empty_text); ?></p>
    <?php else : ?>
        <ul class="products columns-<?php echo esc_attr((string) $polski_grid_columns); ?>">
            <?php foreach ($polski_products as $polski_product) : ?>
                <li class="product">
                    <a href="<?php echo esc_url(get_permalink($polski_product->get_id()) ?: ''); ?>">
                        <?php if ($polski_show_product_image) : ?>
                            <?php echo $polski_product->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endif; ?>
                        <?php if ($polski_show_product_name) : ?>
                            <h3><?php echo esc_html($polski_product->get_name()); ?></h3>
                        <?php endif; ?>
                    </a>
                    <?php if ($polski_show_price && $polski_product->get_price_html() !== '') : ?>
                        <span class="price"><?php echo $polski_product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <?php endif; ?>
                    <?php $polski_button = $polski_service->getButtonData($polski_product); ?>
                    <?php if ($polski_show_add_to_cart && $polski_product->is_purchasable() && $polski_product->is_in_stock() && $polski_product->supports('ajax_add_to_cart')) : ?>
                        <a
                            href="<?php echo esc_url($polski_product->add_to_cart_url()); ?>"
                            data-quantity="1"
                            class="button add_to_cart_button ajax_add_to_cart"
                            data-product_id="<?php echo esc_attr((string) $polski_product->get_id()); ?>"
                            data-product_sku="<?php echo esc_attr($polski_product->get_sku()); ?>"
                            aria-label="<?php echo esc_attr($polski_product->add_to_cart_description()); ?>"
                            rel="nofollow"
                        >
                            <?php echo esc_html($polski_product->add_to_cart_text()); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($polski_show_remove_button) : ?>
                        <button
                            type="button"
                            class="button polski-wishlist-button polski-wishlist-button--loop<?php echo $polski_button['in_wishlist'] ? ' is-active' : ''; ?>"
                            data-polski-wishlist-button
                            data-product-id="<?php echo esc_attr((string) $polski_button['product_id']); ?>"
                        >
                            <?php echo esc_html($polski_button['label']); ?>
                        </button>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
