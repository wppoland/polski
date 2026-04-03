<?php
/**
 * AJAX product search form.
 *
 * @var array<string, mixed> $settings
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<form role="search" method="get" class="woocommerce-product-search polski-ajax-search" action="<?php echo esc_url(home_url('/')); ?>" data-polski-ajax-search>
    <label class="screen-reader-text" for="polski-ajax-search-input">
        <?php echo esc_html((string) ($settings['search_label'] ?? __('Szukaj produktów', 'polski'))); ?>
    </label>
    <div class="polski-ajax-search__inner">
        <input
            type="search"
            id="polski-ajax-search-input"
            class="search-field polski-ajax-search__input"
            placeholder="<?php echo esc_attr((string) ($settings['placeholder'] ?? '')); ?>"
            value="<?php echo esc_attr(get_search_query()); ?>"
            name="s"
            autocomplete="off"
            data-polski-ajax-search-input
        />
        <?php if ((bool) ($settings['show_submit_button'] ?? true)) : ?>
            <button type="submit" class="polski-ajax-search__submit">
                <?php echo esc_html((string) ($settings['submit_button_text'] ?? '')); ?>
            </button>
        <?php endif; ?>
        <input type="hidden" name="post_type" value="product" />
    </div>
    <div
        class="polski-ajax-search__results"
        hidden
        aria-label="<?php echo esc_attr((string) ($settings['results_label'] ?? __('Wyniki wyszukiwania produktów', 'polski'))); ?>"
        data-polski-ajax-search-results
    ></div>
</form>
