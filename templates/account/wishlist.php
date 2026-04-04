<?php
/**
 * Wishlist account view.
 *
 * @var \Polski\Service\WishlistService $service
 * @var list<WC_Product>                     $products
 * @var string                               $title
 * @var string                               $intro_text
 * @var string                               $empty_text
 * @var bool                                 $show_title
 * @var bool                                 $show_product_image
 * @var bool                                 $show_product_name
 * @var bool                                 $show_price
 * @var bool                                 $show_add_to_cart
 * @var bool                                 $show_remove_button
 * @var int                                  $grid_columns
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<div class="polski-wishlist-account">
    <?php if ($show_title) : ?>
        <h2><?php echo esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if ($intro_text !== '') : ?>
        <div class="polski-wishlist-account__intro">
            <?php echo wpautop(wp_kses_post($intro_text)); ?>
        </div>
    <?php endif; ?>

    <?php if ($products === []) : ?>
        <p><?php echo esc_html($empty_text); ?></p>
    <?php else : ?>
        <ul class="products columns-<?php echo esc_attr((string) $grid_columns); ?>">
            <?php foreach ($products as $product) : ?>
                <li class="product">
                    <a href="<?php echo esc_url(get_permalink($product->get_id()) ?: ''); ?>">
                        <?php if ($show_product_image) : ?>
                            <?php echo $product->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endif; ?>
                        <?php if ($show_product_name) : ?>
                            <h3><?php echo esc_html($product->get_name()); ?></h3>
                        <?php endif; ?>
                    </a>
                    <?php if ($show_price && $product->get_price_html() !== '') : ?>
                        <span class="price"><?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <?php endif; ?>
                    <?php $button = $service->getButtonData($product); ?>
                    <?php if ($show_add_to_cart && $product->is_purchasable() && $product->is_in_stock() && $product->supports('ajax_add_to_cart')) : ?>
                        <a
                            href="<?php echo esc_url($product->add_to_cart_url()); ?>"
                            data-quantity="1"
                            class="button add_to_cart_button ajax_add_to_cart"
                            data-product_id="<?php echo esc_attr((string) $product->get_id()); ?>"
                            data-product_sku="<?php echo esc_attr($product->get_sku()); ?>"
                            aria-label="<?php echo esc_attr($product->add_to_cart_description()); ?>"
                            rel="nofollow"
                        >
                            <?php echo esc_html($product->add_to_cart_text()); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($show_remove_button) : ?>
                        <button
                            type="button"
                            class="button polski-wishlist-button polski-wishlist-button--loop<?php echo $button['in_wishlist'] ? ' is-active' : ''; ?>"
                            data-polski-wishlist-button
                            data-product-id="<?php echo esc_attr((string) $button['product_id']); ?>"
                        >
                            <?php echo esc_html($button['label']); ?>
                        </button>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
