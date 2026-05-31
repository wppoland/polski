<?php
/**
 * AJAX product filters form.
 *
 * @var array<string, mixed> $polski_settings
 * @var list<array{term: WP_Term, label: string, depth: int}> $polski_categories
 * @var list<array{term: WP_Term, label: string, depth: int}> $polski_brands
 * @var array<string, list<array{term: WP_Term, label: string, depth: int}>> $polski_attribute_options
 * @var list<string>         $polski_attribute_taxonomies
 * @var string               $polski_action_url
 * @var string               $polski_reset_url
 * @var list<array{param: string, label: string, value: string, raw_value: string, remove_url: string}> $polski_active_filters
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// Read-only filter parameters from URL query string. The form uses GET (storefront browsing),
// so a nonce is not appropriate here per WordPress guidance for read-only navigation.
// All inputs are sanitized before any use or output.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$polski_read_terms = static function (string $polski_key): array {
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised per item below.
    $polski_raw = wp_unslash($_GET[$polski_key] ?? []);

    if (is_string($polski_raw)) {
        $polski_raw = [sanitize_text_field($polski_raw)];
    }

    if (! is_array($polski_raw)) {
        return [];
    }

    $polski_values = array_map(
        static fn (mixed $polski_value): string => sanitize_title((string) $polski_value),
        $polski_raw,
    );

    return array_values(array_filter(array_unique($polski_values)));
};

$polski_filter_category  = $polski_read_terms('polski_filter_category');
$polski_filter_brand     = $polski_read_terms('polski_filter_brand');
$polski_filter_min_price = isset($_GET['polski_filter_min_price']) ? sanitize_text_field((string) wp_unslash($_GET['polski_filter_min_price'])) : '';
$polski_filter_max_price = isset($_GET['polski_filter_max_price']) ? sanitize_text_field((string) wp_unslash($_GET['polski_filter_max_price'])) : '';
$polski_filter_stock     = isset($_GET['polski_filter_stock'])     ? sanitize_key((string) wp_unslash($_GET['polski_filter_stock']))       : '';
$polski_filter_sale      = isset($_GET['polski_filter_sale'])      ? sanitize_key((string) wp_unslash($_GET['polski_filter_sale']))        : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$polski_taxonomy_multiselect = (bool) ($polski_settings['enable_taxonomy_multiselect'] ?? true);
$polski_mobile_panel_enabled = (bool) ($polski_settings['enable_mobile_panel'] ?? true);
$polski_active_filters_count = count($polski_active_filters);

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
<form
    class="polski-ajax-filters"
    method="get"
    action="<?php echo esc_url($polski_action_url); ?>"
    data-polski-ajax-filters
    data-polski-instant-filtering="<?php echo ! empty($polski_settings['enable_instant_filtering']) ? '1' : '0'; ?>"
    data-polski-instant-debounce="<?php echo esc_attr((string) max(0, (int) ($polski_settings['instant_filtering_debounce_ms'] ?? 350))); ?>"
>
    <div
        class="polski-ajax-filters__sr-only"
        data-polski-ajax-filters-status
        role="status"
        aria-live="polite"
        aria-atomic="true"
    ></div>

    <?php if ((bool) ($polski_settings['show_title'] ?? true)) : ?>
        <h3 class="polski-ajax-filters__title"><?php echo esc_html((string) ($polski_settings['title'] ?? '')); ?></h3>
    <?php endif; ?>

    <?php if ($polski_mobile_panel_enabled) : ?>
        <div class="polski-ajax-filters__mobile-toolbar">
            <button
                type="button"
                class="button polski-ajax-filters__mobile-toggle"
                data-polski-ajax-filters-open
                aria-expanded="false"
            >
                <span><?php echo esc_html((string) ($polski_settings['mobile_toggle_text'] ?? __('Pokaż filtry', 'polski'))); ?></span>
                <?php if ($polski_active_filters_count > 0) : ?>
                    <span class="polski-ajax-filters__mobile-count"><?php echo esc_html((string) $polski_active_filters_count); ?></span>
                <?php endif; ?>
            </button>
        </div>
    <?php endif; ?>

    <?php if ((bool) ($polski_settings['show_active_filters'] ?? true) && $polski_active_filters !== []) : ?>
        <div class="polski-ajax-filters__active">
            <span class="polski-ajax-filters__active-label">
                <?php echo esc_html((string) ($polski_settings['active_filters_label'] ?? __('Aktywne filtry', 'polski'))); ?>
            </span>
            <div class="polski-ajax-filters__chips">
                <?php foreach ($polski_active_filters as $polski_filter_item) : ?>
                    <a
                        class="polski-ajax-filters__chip"
                        href="<?php echo esc_url($polski_filter_item['remove_url']); ?>"
                        data-polski-ajax-filters-chip
                    >
                        <span class="polski-ajax-filters__chip-text">
                            <?php echo esc_html($polski_filter_item['label'] . ': ' . $polski_filter_item['value']); ?>
                        </span>
                        <span class="polski-ajax-filters__chip-remove" aria-hidden="true">×</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="polski-ajax-filters__backdrop" data-polski-ajax-filters-close hidden></div>
    <div class="polski-ajax-filters__panel" data-polski-ajax-filters-panel>
        <?php if ($polski_mobile_panel_enabled) : ?>
            <div class="polski-ajax-filters__panel-header">
                <strong><?php echo esc_html((string) ($polski_settings['mobile_panel_title'] ?? ($polski_settings['title'] ?? __('Filtry produktów', 'polski')))); ?></strong>
                <button
                    type="button"
                    class="button button-link"
                    data-polski-ajax-filters-close
                >
                    <?php echo esc_html((string) ($polski_settings['mobile_close_text'] ?? __('Zamknij', 'polski'))); ?>
                </button>
            </div>
        <?php endif; ?>

        <div class="polski-ajax-filters__grid">
            <?php if (! empty($polski_settings['show_categories']) && $polski_categories !== []) : ?>
                <label class="polski-ajax-filters__field">
                    <span><?php echo esc_html((string) ($polski_settings['category_label'] ?? __('Kategoria', 'polski'))); ?></span>
                    <select
                        name="<?php echo esc_attr($polski_taxonomy_multiselect ? 'polski_filter_category[]' : 'polski_filter_category'); ?>"
                        <?php echo $polski_taxonomy_multiselect ? 'multiple size="6"' : ''; ?>
                    >
                        <?php if (! $polski_taxonomy_multiselect) : ?>
                            <option value=""><?php echo esc_html((string) ($polski_settings['category_all_text'] ?? __('Wszystkie', 'polski'))); ?></option>
                        <?php endif; ?>
                        <?php foreach ($polski_categories as $polski_option) : ?>
                            <option value="<?php echo esc_attr($polski_option['term']->slug); ?>" <?php selected(in_array($polski_option['term']->slug, $polski_filter_category, true)); ?>>
                                <?php
                                echo esc_html(
                                    $polski_option['label']
                                    . (! empty($polski_settings['show_counts']) ? ' (' . (int) $polski_option['term']->count . ')' : '')
                                );
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>

            <?php if (! empty($polski_settings['show_brands']) && $polski_brands !== []) : ?>
                <label class="polski-ajax-filters__field">
                    <span><?php echo esc_html((string) ($polski_settings['brand_label'] ?? __('Marka', 'polski'))); ?></span>
                    <select
                        name="<?php echo esc_attr($polski_taxonomy_multiselect ? 'polski_filter_brand[]' : 'polski_filter_brand'); ?>"
                        <?php echo $polski_taxonomy_multiselect ? 'multiple size="6"' : ''; ?>
                    >
                        <?php if (! $polski_taxonomy_multiselect) : ?>
                            <option value=""><?php echo esc_html((string) ($polski_settings['brand_all_text'] ?? __('Wszystkie', 'polski'))); ?></option>
                        <?php endif; ?>
                        <?php foreach ($polski_brands as $polski_option) : ?>
                            <option value="<?php echo esc_attr($polski_option['term']->slug); ?>" <?php selected(in_array($polski_option['term']->slug, $polski_filter_brand, true)); ?>>
                                <?php
                                echo esc_html(
                                    $polski_option['label']
                                    . (! empty($polski_settings['show_counts']) ? ' (' . (int) $polski_option['term']->count . ')' : '')
                                );
                                ?>
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
                <?php $polski_terms = $polski_attribute_options[$polski_taxonomy] ?? []; ?>
                <?php if ($polski_terms === []) : ?>
                    <?php continue; ?>
                <?php endif; ?>
                <?php $polski_param = 'polski_filter_' . $polski_taxonomy; ?>
                <?php $polski_filter_attr_values = $polski_read_terms($polski_param); ?>
                <label class="polski-ajax-filters__field">
                    <span><?php echo esc_html(wc_attribute_label($polski_taxonomy)); ?></span>
                    <select
                        name="<?php echo esc_attr($polski_taxonomy_multiselect ? $polski_param . '[]' : $polski_param); ?>"
                        <?php echo $polski_taxonomy_multiselect ? 'multiple size="6"' : ''; ?>
                    >
                        <?php if (! $polski_taxonomy_multiselect) : ?>
                            <option value=""><?php echo esc_html((string) ($polski_settings['attribute_any_text'] ?? __('Dowolny', 'polski'))); ?></option>
                        <?php endif; ?>
                        <?php foreach ($polski_terms as $polski_option) : ?>
                            <option value="<?php echo esc_attr($polski_option['term']->slug); ?>" <?php selected(in_array($polski_option['term']->slug, $polski_filter_attr_values, true)); ?>>
                                <?php
                                echo esc_html(
                                    $polski_option['label']
                                    . (! empty($polski_settings['show_counts']) ? ' (' . (int) $polski_option['term']->count . ')' : '')
                                );
                                ?>
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
    </div>
</form>
