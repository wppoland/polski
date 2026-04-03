<?php
/**
 * AJAX product filters form.
 *
 * @var array<string, mixed> $settings
 * @var list<WP_Term>        $categories
 * @var list<WP_Term>        $brands
 * @var list<string>         $attribute_taxonomies
 * @var string               $reset_url
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<form class="polski-ajax-filters" method="get" action="<?php echo esc_url(get_permalink(wc_get_page_id('shop')) ?: ''); ?>" data-polski-ajax-filters>
    <?php if ((bool) ($settings['show_title'] ?? true)) : ?>
        <h3 class="polski-ajax-filters__title"><?php echo esc_html((string) ($settings['title'] ?? '')); ?></h3>
    <?php endif; ?>

    <div class="polski-ajax-filters__grid">
        <?php if (! empty($settings['show_categories']) && $categories !== []) : ?>
            <label class="polski-ajax-filters__field">
                <span><?php echo esc_html((string) ($settings['category_label'] ?? __('Kategoria', 'polski'))); ?></span>
                <select name="polski_filter_category">
                    <option value=""><?php echo esc_html((string) ($settings['category_all_text'] ?? __('Wszystkie', 'polski'))); ?></option>
                    <?php foreach ($categories as $term) : ?>
                        <option value="<?php echo esc_attr($term->slug); ?>" <?php selected(sanitize_title((string) wp_unslash($_GET['polski_filter_category'] ?? '')), $term->slug); ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>

        <?php if (! empty($settings['show_brands']) && $brands !== []) : ?>
            <label class="polski-ajax-filters__field">
                <span><?php echo esc_html((string) ($settings['brand_label'] ?? __('Marka', 'polski'))); ?></span>
                <select name="polski_filter_brand">
                    <option value=""><?php echo esc_html((string) ($settings['brand_all_text'] ?? __('Wszystkie', 'polski'))); ?></option>
                    <?php foreach ($brands as $term) : ?>
                        <option value="<?php echo esc_attr($term->slug); ?>" <?php selected(sanitize_title((string) wp_unslash($_GET['polski_filter_brand'] ?? '')), $term->slug); ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>

        <?php if (! empty($settings['show_price'])) : ?>
            <label class="polski-ajax-filters__field">
                <span><?php echo esc_html((string) ($settings['min_price_label'] ?? __('Cena od', 'polski'))); ?></span>
                <input type="number" step="0.01" min="0" name="polski_filter_min_price" value="<?php echo esc_attr((string) wp_unslash($_GET['polski_filter_min_price'] ?? '')); ?>">
            </label>
            <label class="polski-ajax-filters__field">
                <span><?php echo esc_html((string) ($settings['max_price_label'] ?? __('Cena do', 'polski'))); ?></span>
                <input type="number" step="0.01" min="0" name="polski_filter_max_price" value="<?php echo esc_attr((string) wp_unslash($_GET['polski_filter_max_price'] ?? '')); ?>">
            </label>
        <?php endif; ?>

        <?php if (! empty($settings['show_stock'])) : ?>
            <label class="polski-ajax-filters__field">
                <span><?php echo esc_html((string) ($settings['stock_label'] ?? __('Dostępność', 'polski'))); ?></span>
                <select name="polski_filter_stock">
                    <option value=""><?php echo esc_html((string) ($settings['stock_any_text'] ?? __('Dowolna', 'polski'))); ?></option>
                    <option value="instock" <?php selected(sanitize_key((string) wp_unslash($_GET['polski_filter_stock'] ?? '')), 'instock'); ?>>
                        <?php echo esc_html((string) ($settings['stock_instock_text'] ?? __('Dostępne od ręki', 'polski'))); ?>
                    </option>
                </select>
            </label>
        <?php endif; ?>

        <?php if (! empty($settings['show_sale'])) : ?>
            <label class="polski-ajax-filters__field polski-ajax-filters__field--checkbox">
                <span><?php echo esc_html((string) ($settings['sale_label'] ?? __('Promocje', 'polski'))); ?></span>
                <input type="checkbox" name="polski_filter_sale" value="1" <?php checked(sanitize_key((string) wp_unslash($_GET['polski_filter_sale'] ?? '')), '1'); ?>>
            </label>
        <?php endif; ?>

        <?php foreach ($attribute_taxonomies as $taxonomy) : ?>
            <?php $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]); ?>
            <?php if (! is_array($terms) || $terms === []) : ?>
                <?php continue; ?>
            <?php endif; ?>
            <?php $param = 'polski_filter_' . $taxonomy; ?>
            <label class="polski-ajax-filters__field">
                <span><?php echo esc_html(wc_attribute_label($taxonomy)); ?></span>
                <select name="<?php echo esc_attr($param); ?>">
                    <option value=""><?php echo esc_html((string) ($settings['attribute_any_text'] ?? __('Dowolny', 'polski'))); ?></option>
                    <?php foreach ($terms as $term) : ?>
                        <?php if (! $term instanceof WP_Term) : ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <option value="<?php echo esc_attr($term->slug); ?>" <?php selected(sanitize_title((string) wp_unslash($_GET[$param] ?? '')), $term->slug); ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="polski-ajax-filters__actions">
        <button type="submit" class="button">
            <?php echo esc_html((string) ($settings['submit_text'] ?? '')); ?>
        </button>
        <?php if (! empty($settings['show_reset_link'])) : ?>
            <a class="button button-link-delete" href="<?php echo esc_url($reset_url); ?>" data-polski-ajax-filters-reset>
                <?php echo esc_html((string) ($settings['reset_text'] ?? '')); ?>
            </a>
        <?php endif; ?>
    </div>
</form>
