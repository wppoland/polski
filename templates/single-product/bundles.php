<?php

declare(strict_types=1);

/**
 * @var \Polski\Service\ProductBundlesService $service
 * @var \WC_Product                                $product
 * @var list<array{product:\WC_Product,quantity:int,required:bool}> $bundle_items
 * @var array<string, mixed>                       $settings
 * @var string                                     $title
 * @var string                                     $intro_text
 * @var string                                     $button_text
 * @var bool                                       $show_total
 * @var bool                                       $show_quantities
 * @var array{base_total:float,discount_total:float,final_total:float,savings_html:string,total_html:string} $pricing
 */
?>
<section class="polski-bundles">
    <h3 class="polski-bundles__title"><?php echo esc_html($title); ?></h3>

    <?php if ($intro_text !== '') : ?>
        <p class="polski-bundles__intro"><?php echo esc_html($intro_text); ?></p>
    <?php endif; ?>

    <form method="post" class="polski-bundles__form">
        <ul class="polski-bundles__items">
            <li class="polski-bundles__item polski-bundles__item--main">
                <span class="polski-bundles__name"><?php echo esc_html($product->get_name()); ?></span>
                <span class="polski-bundles__qty"><?php echo esc_html((string) ($settings['included_label'] ?? __('w zestawie', 'polski'))); ?></span>
                <span class="polski-bundles__price"><?php echo wp_kses_post(wc_price((float) wc_get_price_to_display($product))); ?></span>
            </li>
            <?php foreach ($bundle_items as $item) : ?>
                <?php $bundleProduct = $item['product']; ?>
                <li class="polski-bundles__item">
                    <label class="polski-bundles__label">
                        <?php if ($item['required']) : ?>
                            <input type="checkbox" checked="checked" disabled="disabled" />
                            <input type="hidden" name="<?php echo esc_attr('polski_bundle_include_' . $bundleProduct->get_id()); ?>" value="1" />
                        <?php else : ?>
                            <input type="checkbox" name="<?php echo esc_attr('polski_bundle_include_' . $bundleProduct->get_id()); ?>" value="1" checked="checked" />
                        <?php endif; ?>
                        <span class="polski-bundles__name"><?php echo esc_html($bundleProduct->get_name()); ?></span>
                    </label>
                    <?php if ($show_quantities) : ?>
                        <span class="polski-bundles__qty">
                            <?php
                            echo esc_html(
                                str_replace(
                                    '{quantity}',
                                    (string) ((int) $item['quantity']),
                                    (string) ($settings['quantity_format'] ?? __('x {quantity}', 'polski')),
                                ),
                            );
                            ?>
                        </span>
                    <?php endif; ?>
                    <span class="polski-bundles__price">
                        <?php echo wp_kses_post(wc_price((float) wc_get_price_to_display($bundleProduct) * max(1, (int) $item['quantity']))); ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($show_total) : ?>
            <div class="polski-bundles__summary">
                <span><?php echo esc_html((string) ($settings['bundle_total_label'] ?? __('Cena zestawu', 'polski'))); ?>:</span>
                <strong><?php echo wp_kses_post($pricing['total_html']); ?></strong>
                <?php if ($pricing['discount_total'] > 0) : ?>
                    <span class="polski-bundles__savings">
                        <?php
                        printf(
                            /* translators: 1: label, 2: savings amount */
                            esc_html__('%1$s %2$s', 'polski'),
                            esc_html((string) ($service->getSettings()['discount_label'] ?? __('Oszczędzasz', 'polski'))),
                            wp_kses_post($pricing['savings_html']),
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php wp_nonce_field('polski_bundle_add', 'polski_bundle_nonce'); ?>
        <input type="hidden" name="polski_bundle_action" value="add_bundle" />
        <input type="hidden" name="polski_bundle_product_id" value="<?php echo esc_attr((string) $product->get_id()); ?>" />
        <button type="submit" class="button alt polski-bundles__button"><?php echo esc_html($button_text); ?></button>
    </form>
</section>
