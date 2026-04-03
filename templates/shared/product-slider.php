<?php
/**
 * Product slider.
 *
 * @var list<WC_Product>    $products
 * @var array<string,mixed> $settings
 * @var string              $title
 * @var bool                $show_title
 * @var bool                $show_intro_text
 * @var string              $intro_text
 * @var bool                $show_image
 * @var bool                $show_name
 * @var bool                $show_price
 * @var bool                $show_add_to_cart
 * @var bool                $show_view_all_link
 * @var bool                $show_empty_state
 * @var string              $empty_text
 * @var string              $view_all_url
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<section class="polski-product-slider">
    <?php if ($show_title) : ?>
        <h2><?php echo esc_html($title); ?></h2>
    <?php endif; ?>
    <?php if ($show_intro_text && $intro_text !== '') : ?>
        <p class="polski-product-slider__intro"><?php echo esc_html($intro_text); ?></p>
    <?php endif; ?>
    <?php if ($products === []) : ?>
        <?php if ($show_empty_state && $empty_text !== '') : ?>
            <p class="polski-product-slider__empty"><?php echo esc_html($empty_text); ?></p>
        <?php endif; ?>
    <?php else : ?>
        <div class="polski-product-slider__track">
            <?php foreach ($products as $product) : ?>
                <article class="polski-product-slider__item product">
                    <a href="<?php echo esc_url(get_permalink($product->get_id()) ?: ''); ?>">
                        <?php if ($show_image) : ?>
                            <?php echo $product->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endif; ?>
                        <?php if ($show_name) : ?>
                            <h3><?php echo esc_html($product->get_name()); ?></h3>
                        <?php endif; ?>
                    </a>
                    <?php if ($show_price && $product->get_price_html() !== '') : ?>
                        <div class="price"><?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php endif; ?>
                    <?php if ($show_add_to_cart && $product->is_purchasable()) : ?>
                        <a
                            href="<?php echo esc_url($product->add_to_cart_url()); ?>"
                            data-quantity="1"
                            class="button<?php echo $product->supports('ajax_add_to_cart') ? ' add_to_cart_button ajax_add_to_cart' : ''; ?>"
                            data-product_id="<?php echo esc_attr((string) $product->get_id()); ?>"
                            data-product_sku="<?php echo esc_attr($product->get_sku()); ?>"
                            aria-label="<?php echo esc_attr($product->add_to_cart_description()); ?>"
                            rel="nofollow"
                        >
                            <?php echo esc_html($product->add_to_cart_text()); ?>
                        </a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($show_view_all_link && $view_all_url !== '') : ?>
        <p class="polski-product-slider__footer">
            <a
                href="<?php echo esc_url($view_all_url); ?>"
                <?php echo (($settings['view_all_target'] ?? 'same_tab') === 'new_tab') ? 'target="_blank" rel="noopener noreferrer"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            >
                <?php echo esc_html((string) ($settings['view_all_text'] ?? __('Zobacz wszystkie produkty', 'polski'))); ?>
            </a>
        </p>
    <?php endif; ?>
</section>
