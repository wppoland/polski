<?php
/**
 * Compare account view.
 *
 * @var \Polski\Service\CompareService $service
 * @var list<WC_Product>                    $products
 * @var list<array{key: string, label: string, values: array<int, string>, text_values: array<int, string>}> $rows
 * @var array<string, bool>                 $differences
 * @var string                              $title
 * @var string                              $intro_text
 * @var string                              $empty_text
 * @var bool                                $show_only_differences
 * @var bool                                $highlight_differences
 * @var string                              $feature_label
 * @var string                              $differences_toggle_text
 * @var bool                                $show_product_image
 * @var bool                                $show_add_to_cart
 * @var bool                                $show_remove_button
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<div class="polski-compare-account">
    <div class="polski-compare-account__header">
        <h2><?php echo esc_html($title); ?></h2>

        <?php if ($intro_text !== '') : ?>
            <div class="polski-compare-account__intro"><?php echo wp_kses_post(wpautop(wp_kses_post($intro_text))); ?></div>
        <?php endif; ?>

        <?php if ($products !== []) : ?>
            <div class="polski-compare-actions">
                <label class="polski-compare-toggle">
                    <input type="checkbox" data-polski-compare-differences <?php checked($show_only_differences); ?> />
                    <span><?php echo esc_html($differences_toggle_text); ?></span>
                </label>

                <button type="button" class="button" data-polski-compare-clear>
                    <?php echo esc_html($service->getClearText()); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($products === []) : ?>
        <p><?php echo esc_html($empty_text); ?></p>
    <?php else : ?>
        <div class="polski-compare-table-wrapper">
            <table class="shop_table shop_table_responsive polski-compare-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html($feature_label); ?></th>
                        <?php foreach ($products as $product) : ?>
                            <?php $button = $service->getButtonData($product); ?>
                            <th class="polski-compare-product">
                                <a href="<?php echo esc_url(get_permalink($product->get_id()) ?: ''); ?>">
                                    <?php if ($show_product_image) : ?>
                                        <?php echo $product->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <?php endif; ?>
                                    <span class="polski-compare-product__name"><?php echo esc_html($product->get_name()); ?></span>
                                </a>
                                <div class="polski-compare-product__actions">
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
                                            class="button polski-compare-button is-active"
                                            data-polski-compare-button
                                            data-product-id="<?php echo esc_attr((string) $button['product_id']); ?>"
                                        >
                                            <?php echo esc_html($button['label']); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php $isDifferent = $differences[$row['key']] ?? false; ?>
                        <tr
                            data-different="<?php echo $isDifferent ? '1' : '0'; ?>"
                            class="<?php echo esc_attr($highlight_differences && $isDifferent ? 'is-different' : ''); ?>"
                        >
                            <th><?php echo esc_html($row['label']); ?></th>
                            <?php foreach ($row['values'] as $value) : ?>
                                <td><?php echo $value !== '-' ? wp_kses_post($value) : esc_html($value); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
