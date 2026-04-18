<?php
/**
 * Product slider.
 *
 * @var list<WC_Product>    $polski_products
 * @var array<string,mixed> $polski_settings
 * @var string              $polski_title
 * @var bool                $polski_show_title
 * @var bool                $polski_show_intro_text
 * @var string              $polski_intro_text
 * @var bool                $polski_show_image
 * @var bool                $polski_show_name
 * @var bool                $polski_show_price
 * @var bool                $polski_show_add_to_cart
 * @var bool                $polski_show_view_all_link
 * @var bool                $polski_show_empty_state
 * @var string              $polski_empty_text
 * @var string              $polski_view_all_url
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<section class="polski-product-slider">
    <?php if ($polski_show_title) : ?>
        <h2><?php echo esc_html($polski_title); ?></h2>
    <?php endif; ?>
    <?php if ($polski_show_intro_text && $polski_intro_text !== '') : ?>
        <p class="polski-product-slider__intro"><?php echo esc_html($polski_intro_text); ?></p>
    <?php endif; ?>
    <?php if ($polski_products === []) : ?>
        <?php if ($polski_show_empty_state && $polski_empty_text !== '') : ?>
            <p class="polski-product-slider__empty"><?php echo esc_html($polski_empty_text); ?></p>
        <?php endif; ?>
    <?php else : ?>
        <div class="polski-product-slider__track">
            <?php foreach ($polski_products as $polski_product) : ?>
                <article class="polski-product-slider__item product">
                    <a href="<?php echo esc_url(get_permalink($polski_product->get_id()) ?: ''); ?>">
                        <?php if ($polski_show_image) : ?>
                            <?php echo $polski_product->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endif; ?>
                        <?php if ($polski_show_name) : ?>
                            <h3><?php echo esc_html($polski_product->get_name()); ?></h3>
                        <?php endif; ?>
                    </a>
                    <?php if ($polski_show_price && $polski_product->get_price_html() !== '') : ?>
                        <div class="price"><?php echo $polski_product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php endif; ?>
                    <?php if ($polski_show_add_to_cart && $polski_product->is_purchasable()) : ?>
                        <a
                            href="<?php echo esc_url($polski_product->add_to_cart_url()); ?>"
                            data-quantity="1"
                            class="button<?php echo $polski_product->supports('ajax_add_to_cart') ? ' add_to_cart_button ajax_add_to_cart' : ''; ?>"
                            data-product_id="<?php echo esc_attr((string) $polski_product->get_id()); ?>"
                            data-product_sku="<?php echo esc_attr($polski_product->get_sku()); ?>"
                            aria-label="<?php echo esc_attr($polski_product->add_to_cart_description()); ?>"
                            rel="nofollow"
                        >
                            <?php echo esc_html($polski_product->add_to_cart_text()); ?>
                        </a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($polski_show_view_all_link && $polski_view_all_url !== '') : ?>
        <p class="polski-product-slider__footer">
            <a
                href="<?php echo esc_url($polski_view_all_url); ?>"
                <?php echo (($polski_settings['view_all_target'] ?? 'same_tab') === 'new_tab') ? 'target="_blank" rel="noopener noreferrer"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            >
                <?php echo esc_html((string) ($polski_settings['view_all_text'] ?? __('Zobacz wszystkie produkty', 'polski'))); ?>
            </a>
        </p>
    <?php endif; ?>
</section>
