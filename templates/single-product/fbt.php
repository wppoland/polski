<?php
/**
 * Frequently bought together section.
 *
 * @var \Polski\Service\FrequentlyBoughtTogetherService $service
 * @var \WC_Product                                          $product
 * @var list<WC_Product>                                     $bundle_products
 * @var string                                               $title
 * @var string                                               $intro_text
 * @var string                                               $button_text
 * @var string                                               $empty_text
 * @var bool                                                 $show_title
 * @var bool                                                 $show_price
 * @var bool                                                 $show_total
 * @var bool                                                 $show_images
 * @var bool                                                 $show_checkboxes
 * @var bool                                                 $preselect_products
 * @var bool                                                 $show_short_description
 * @var string                                               $total_html
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<section class="polski-fbt">
    <?php if ($show_title) : ?>
        <h2><?php echo esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if ($intro_text !== '') : ?>
        <p class="polski-fbt__intro"><?php echo esc_html($intro_text); ?></p>
    <?php endif; ?>

    <?php if ($bundle_products === []) : ?>
        <p><?php echo esc_html($empty_text); ?></p>
    <?php else : ?>
        <form method="post" class="polski-fbt-form">
            <input type="hidden" name="polski_fbt_action" value="add_bundle" />
            <input type="hidden" name="polski_fbt_redirect" value="<?php echo esc_url(get_permalink($product->get_id()) ?: ''); ?>" />
            <?php wp_nonce_field('polski_fbt_add_bundle', 'polski_fbt_nonce'); ?>

            <ul class="polski-fbt-products">
                <?php foreach ($bundle_products as $bundleProduct) : ?>
                    <li class="polski-fbt-product">
                        <label>
                            <?php if ($show_checkboxes) : ?>
                                <input type="checkbox" name="polski_fbt_products[]" value="<?php echo esc_attr((string) $bundleProduct->get_id()); ?>" <?php checked($preselect_products); ?> data-polski-fbt-checkbox />
                            <?php else : ?>
                                <input type="hidden" name="polski_fbt_products[]" value="<?php echo esc_attr((string) $bundleProduct->get_id()); ?>" />
                                <input type="checkbox" value="<?php echo esc_attr((string) $bundleProduct->get_id()); ?>" <?php checked($preselect_products); ?> data-polski-fbt-checkbox hidden />
                            <?php endif; ?>
                            <span class="polski-fbt-product__inner">
                                <?php if ($show_images) : ?>
                                    <span class="polski-fbt-product__image">
                                        <?php echo $bundleProduct->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </span>
                                <?php endif; ?>
                                <span class="polski-fbt-product__content">
                                    <a href="<?php echo esc_url(get_permalink($bundleProduct->get_id()) ?: ''); ?>" class="polski-fbt-product__name">
                                        <?php echo esc_html($bundleProduct->get_name()); ?>
                                    </a>
                                    <?php if ($show_price && $bundleProduct->get_price_html() !== '') : ?>
                                        <span class="polski-fbt-product__price" data-polski-fbt-price="<?php echo esc_attr((string) wc_get_price_to_display($bundleProduct)); ?>">
                                            <?php echo $bundleProduct->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($show_short_description && $bundleProduct->get_short_description() !== '') : ?>
                                        <span class="polski-fbt-product__description">
                                            <?php echo esc_html(wp_trim_words(wp_strip_all_tags($bundleProduct->get_short_description()), 18)); ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="polski-fbt-summary">
                <?php if ($show_total) : ?>
                    <p class="polski-fbt-summary__total">
                        <span><?php echo esc_html((string) ($settings['total_label'] ?? __('Łącznie:', 'polski'))); ?></span>
                        <strong data-polski-fbt-total data-polski-fbt-currency="<?php echo esc_attr(get_woocommerce_currency()); ?>">
                            <?php echo wp_kses_post($total_html); ?>
                        </strong>
                    </p>
                <?php endif; ?>

                <button type="submit" class="button alt"><?php echo esc_html($button_text); ?></button>
            </div>
        </form>
    <?php endif; ?>
</section>
