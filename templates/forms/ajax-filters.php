<?php
/**
 * AJAX product filters form.
 *
 * @var array<string, mixed> $polski_settings
 * @var list<WP_Term>        $polski_categories
 * @var list<WP_Term>        $polski_brands
 * @var list<string>         $polski_attribute_taxonomies
 * @var string               $polski_action_url
 * @var string               $polski_reset_url
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// Read-only filter parameters from URL query string. The form uses GET (storefront browsing),
// so a nonce is not appropriate here per WordPress guidance for read-only navigation.
// All inputs are sanitized before any use or output.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$polski_filter_category  = isset($_GET['polski_filter_category'])  ? sanitize_title((string) wp_unslash($_GET['polski_filter_category']))  : '';
$polski_filter_brand     = isset($_GET['polski_filter_brand'])     ? sanitize_title((string) wp_unslash($_GET['polski_filter_brand']))     : '';
$polski_filter_min_price = isset($_GET['polski_filter_min_price']) ? sanitize_text_field((string) wp_unslash($_GET['polski_filter_min_price'])) : '';
$polski_filter_max_price = isset($_GET['polski_filter_max_price']) ? sanitize_text_field((string) wp_unslash($_GET['polski_filter_max_price'])) : '';
$polski_filter_stock     = isset($_GET['polski_filter_stock'])     ? sanitize_key((string) wp_unslash($_GET['polski_filter_stock']))       : '';
$polski_filter_sale      = isset($_GET['polski_filter_sale'])      ? sanitize_key((string) wp_unslash($_GET['polski_filter_sale']))        : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// Numeric coercion for price inputs (allows decimals; empty string preserved when not provided).
if ($polski_filter_min_price !== '' && is_numeric($polski_filter_min_price)) {
    $polski_filter_min_price = (string) (float) $polski_filter_min_price;
} else {
    $polski_filter_min_price = '';
}

if ($polski_filter_max_price !== '' && is_numeric($polski_filter_max_price)) {
    $polski_filter_max_price = (string) (float) $polski_filter_max_price;
} else {
    $polski_filter_max_price = '';
}

?>
<form class="polski-ajax-filters" method="get" action="<?php echo esc_url($polski_action_url); ?>" data-polski-ajax-filters>
    <?php if ((bool) ($polski_settings['show_title'] ?? true)) : ?>
        <h3 class="polski-ajax-filters__title"><?php echo esc_html((string) ($polski_settings['title'] ?? '')); ?></h3>
    <?php endif; ?>

    <div class="polski-ajax-filters__grid">
        <?php if (! empty($polski_settings['show_categories']) && $polski_categories !== []) : ?>
            <label class="polski-ajax-filters__field">
                <span><?php echo esc_html((string) ($polski_settings['category_label'] ?? __('Kategoria', 'polski'))); ?></span>
                <select name="polski_filter_category">
                    <option value=""><?php echo esc_html((string) ($polski_settings['category_all_text'] ?? __('Wszystkie', 'polski'))); ?></option>
                    <?php foreach ($polski_categories as $polski_term) : ?>
                        <option value="<?php echo esc_attr($polski_term->slug); ?>" <?php selected($polski_filter_category, $polski_term->slug); ?>>
                            <?php echo esc_html($polski_term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>

        <?php if (! empty($polski_settings['show_brands']) && $polski_brands !== []) : ?>
            <label class="polski-ajax-filters__field">
                <span><?php echo esc_html((string) ($polski_settings['brand_label'] ?? __('Marka', 'polski'))); ?></span>
                <select name="polski_filter_brand">
                    <option value=""><?php echo esc_html((string) ($polski_settings['brand_all_text'] ?? __('Wszystkie', 'polski'))); ?></option>
                    <?php foreach ($polski_brands as $polski_term) : ?>
                        <option value="<?php echo esc_attr($polski_term->slug); ?>" <?php selected($polski_filter_brand, $polski_term->slug); ?>>
                            <?php echo esc_html($polski_term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>

        <?php if (! empty($polski_settings['show_price'])) : ?>
            <label class="polski-ajax-filters__field">
                <span><?php echo esc_html((string) ($polski_settings['min_price_label'] ?? __('Cena od', 'polski'))); ?></span>
                <input type="number" step="0.01" min="0" name="polski_filter_min_price" value="<?php echo esc_attr($polski_filter_min_price); ?>">
            </label>
            <label class="polski-ajax-filters__field">
                <span><?php echo esc_html((string) ($polski_settings['max_price_label'] ?? __('Cena do', 'polski'))); ?></span>
                <input type="number" step="0.01" min="0" name="polski_filter_max_price" value="<?php echo esc_attr($polski_filter_max_price); ?>">
            </label>
        <?php endif; ?>

        <?php if (! empty($polski_settings['show_stock'])) : ?>
            <label class="polski-ajax-filters__field">
                <span><?php echo esc_html((string) ($polski_settings['stock_label'] ?? __('Dostępność', 'polski'))); ?></span>
                <select name="polski_filter_stock">
                    <option value=""><?php echo esc_html((string) ($polski_settings['stock_any_text'] ?? __('Dowolna', 'polski'))); ?></option>
                    <option value="instock" <?php selected($polski_filter_stock, 'instock'); ?>>
                        <?php echo esc_html((string) ($polski_settings['stock_instock_text'] ?? __('Dostępne od ręki', 'polski'))); ?>
                    </option>
                </select>
            </label>
        <?php endif; ?>

        <?php if (! empty($polski_settings['show_sale'])) : ?>
            <label class="polski-ajax-filters__field polski-ajax-filters__field--checkbox">
                <span><?php echo esc_html((string) ($polski_settings['sale_label'] ?? __('Promocje', 'polski'))); ?></span>
                <input type="checkbox" name="polski_filter_sale" value="1" <?php checked($polski_filter_sale, '1'); ?>>
            </label>
        <?php endif; ?>

        <?php foreach ($polski_attribute_taxonomies as $polski_taxonomy) : ?>
            <?php $polski_terms = get_terms(['taxonomy' => $polski_taxonomy, 'hide_empty' => true]); ?>
            <?php if (! is_array($polski_terms) || $polski_terms === []) : ?>
                <?php continue; ?>
            <?php endif; ?>
            <?php $polski_param = 'polski_filter_' . $polski_taxonomy; ?>
            <?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter, sanitized below. ?>
            <?php $polski_filter_attr_value = isset($_GET[$polski_param]) ? sanitize_title((string) wp_unslash($_GET[$polski_param])) : ''; ?>
            <?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>
            <label class="polski-ajax-filters__field">
                <span><?php echo esc_html(wc_attribute_label($polski_taxonomy)); ?></span>
                <select name="<?php echo esc_attr($polski_param); ?>">
                    <option value=""><?php echo esc_html((string) ($polski_settings['attribute_any_text'] ?? __('Dowolny', 'polski'))); ?></option>
                    <?php foreach ($polski_terms as $polski_term) : ?>
                        <?php if (! $polski_term instanceof WP_Term) : ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <option value="<?php echo esc_attr($polski_term->slug); ?>" <?php selected($polski_filter_attr_value, $polski_term->slug); ?>>
                            <?php echo esc_html($polski_term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="polski-ajax-filters__actions">
        <button type="submit" class="button">
            <?php echo esc_html((string) ($polski_settings['submit_text'] ?? '')); ?>
        </button>
        <?php if (! empty($polski_settings['show_reset_link'])) : ?>
            <a class="button button-link-delete" href="<?php echo esc_url($polski_reset_url); ?>" data-polski-ajax-filters-reset>
                <?php echo esc_html((string) ($polski_settings['reset_text'] ?? '')); ?>
            </a>
        <?php endif; ?>
    </div>
</form>
