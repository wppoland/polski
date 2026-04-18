<?php
/**
 * Compare account view.
 *
 * @var \Polski\Service\CompareService $polski_service
 * @var list<WC_Product>                    $polski_products
 * @var list<array{key: string, label: string, values: array<int, string>, text_values: array<int, string>}> $polski_rows
 * @var array<string, bool>                 $polski_differences
 * @var string                              $polski_title
 * @var string                              $polski_intro_text
 * @var string                              $polski_empty_text
 * @var bool                                $polski_show_only_differences
 * @var bool                                $polski_highlight_differences
 * @var string                              $polski_feature_label
 * @var string                              $polski_differences_toggle_text
 * @var bool                                $polski_show_product_image
 * @var bool                                $polski_show_add_to_cart
 * @var bool                                $polski_show_remove_button
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<div class="polski-compare-account">
    <div class="polski-compare-account__header">
        <h2><?php echo esc_html($polski_title); ?></h2>

        <?php if ($polski_intro_text !== '') : ?>
            <div class="polski-compare-account__intro"><?php echo wp_kses_post(wpautop(wp_kses_post($polski_intro_text))); ?></div>
        <?php endif; ?>

        <?php if ($polski_products !== []) : ?>
            <div class="polski-compare-actions">
                <label class="polski-compare-toggle">
                    <input type="checkbox" data-polski-compare-differences <?php checked($polski_show_only_differences); ?> />
                    <span><?php echo esc_html($polski_differences_toggle_text); ?></span>
                </label>

                <button type="button" class="button" data-polski-compare-clear>
                    <?php echo esc_html($polski_service->getClearText()); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($polski_products === []) : ?>
        <p><?php echo esc_html($polski_empty_text); ?></p>
    <?php else : ?>
        <div class="polski-compare-table-wrapper">
            <table class="shop_table shop_table_responsive polski-compare-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html($polski_feature_label); ?></th>
                        <?php foreach ($polski_products as $polski_product) : ?>
                            <?php $polski_button = $polski_service->getButtonData($polski_product); ?>
                            <th class="polski-compare-product">
                                <a href="<?php echo esc_url(get_permalink($polski_product->get_id()) ?: ''); ?>">
                                    <?php if ($polski_show_product_image) : ?>
                                        <?php echo $polski_product->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <?php endif; ?>
                                    <span class="polski-compare-product__name"><?php echo esc_html($polski_product->get_name()); ?></span>
                                </a>
                                <div class="polski-compare-product__actions">
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
                                            class="button polski-compare-button is-active"
                                            data-polski-compare-button
                                            data-product-id="<?php echo esc_attr((string) $polski_button['product_id']); ?>"
                                        >
                                            <?php echo esc_html($polski_button['label']); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($polski_rows as $polski_row) : ?>
                        <?php $polski_isDifferent = $polski_differences[$polski_row['key']] ?? false; ?>
                        <tr
                            data-different="<?php echo $polski_isDifferent ? '1' : '0'; ?>"
                            class="<?php echo esc_attr($polski_highlight_differences && $polski_isDifferent ? 'is-different' : ''); ?>"
                        >
                            <th><?php echo esc_html($polski_row['label']); ?></th>
                            <?php foreach ($polski_row['values'] as $polski_value) : ?>
                                <td><?php echo $polski_value !== '-' ? wp_kses_post($polski_value) : esc_html($polski_value); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
