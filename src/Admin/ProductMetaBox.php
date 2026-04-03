<?php

declare(strict_types=1);

namespace Polski\Admin;

use Polski\Contract\HasHooks;

/**
 * WooCommerce product edit screen meta boxes for Polski fields.
 *
 * Adds a "Polski" tab to the product data panel with:
 * - Unit price fields (base amount, unit, product amount)
 * - Delivery time selector
 * - Manufacturer selector
 */
final class ProductMetaBox implements HasHooks
{
    public function registerHooks(): void
    {
        // Add tab to WooCommerce product data tabs.
        add_filter('woocommerce_product_data_tabs', [$this, 'addProductTab']);

        // Render tab content.
        add_action('woocommerce_product_data_panels', [$this, 'renderProductPanel']);

        // Save meta on product save.
        add_action('woocommerce_process_product_meta', [$this, 'saveProductMeta']);

        // Variable product fields.
        add_action('woocommerce_product_after_variable_attributes', [$this, 'renderVariationFields'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'saveVariationMeta'], 10, 2);
    }

    /**
     * Add "Polski" tab to product data panel.
     *
     * @param array<string, array<string, mixed>> $tabs
     * @return array<string, array<string, mixed>>
     */
    public function addProductTab(array $tabs): array
    {
        $tabs['polski'] = [
            'label' => __('Polski', 'polski'),
            'target' => 'polski_product_data',
            'class' => ['show_if_simple', 'show_if_variable', 'show_if_external'],
            'priority' => 80,
        ];

        return $tabs;
    }

    /**
     * Render the Polski product data panel.
     */
    public function renderProductPanel(): void
    {
        global $post;

        $productId = $post->ID ?? 0;

        echo '<div id="polski_product_data" class="panel woocommerce_options_panel">';

        // --- Unit Price Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Cena jednostkowa', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_unit_price_product_amount',
            'label' => __('Ilość produktu', 'polski'),
            'description' => __('Ilość produktu w opakowaniu (np. 500 dla 500g).', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_unit_price_base',
            'label' => __('Ilość bazowa', 'polski'),
            'description' => __('The reference base amount (e.g., 1 for "per 1 kg", 100 for "per 100 ml").', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        ]);

        // Unit selector (from taxonomy).
        $units = get_terms([
            'taxonomy' => 'polski_unit',
            'hide_empty' => false,
        ]);

        $unitOptions = ['' => __('- Select unit -', 'polski')];

        if (is_array($units)) {
            foreach ($units as $term) {
                if ($term instanceof \WP_Term) {
                    $unitOptions[$term->slug] = $term->name;
                }
            }
        }

        woocommerce_wp_select([
            'id' => '_polski_unit_price_unit',
            'label' => __('Jednostka', 'polski'),
            'options' => $unitOptions,
            'description' => __('Jednostka miary dla wyświetlania ceny jednostkowej.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Delivery Time Section ---
        echo '<div class="options_group">';

        $deliveryTimes = get_terms([
            'taxonomy' => 'polski_delivery_time',
            'hide_empty' => false,
        ]);

        $dtOptions = ['' => __('- Use default -', 'polski')];

        if (is_array($deliveryTimes)) {
            foreach ($deliveryTimes as $term) {
                if ($term instanceof \WP_Term) {
                    $dtOptions[(string) $term->term_id] = $term->name;
                }
            }
        }

        woocommerce_wp_select([
            'id' => '_polski_delivery_time_id',
            'label' => __('Czas dostawy', 'polski'),
            'options' => $dtOptions,
            'description' => __('Szacowany czas dostawy wyświetlany na stronie produktu.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Withdrawal Section ---
        echo '<div class="options_group">';

        woocommerce_wp_checkbox([
            'id' => '_polski_withdrawal_exempt',
            'label' => __('Wyłączenie z prawa odstąpienia', 'polski'),
            'description' => __('Ten produkt jest wyłączony z 14-dniowego prawa odstąpienia (np. treści cyfrowe, towary łatwo psujące sie).', 'polski'),
        ]);

        echo '</div>';

        // --- Quote Request Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Request a Quote', 'polski') . '</h4>';

        woocommerce_wp_checkbox([
            'id' => '_polski_quote_enabled',
            'label' => __('Enable quote form', 'polski'),
            'description' => __('Show the quote request form for this product even if the module is limited to selected products.', 'polski'),
        ]);

        woocommerce_wp_checkbox([
            'id' => '_polski_quote_only',
            'label' => __('Quote only', 'polski'),
            'description' => __('Disable direct purchase and collect enquiries instead of add-to-cart.', 'polski'),
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_quote_min_qty',
            'label' => __('Minimum quote quantity', 'polski'),
            'description' => __('Minimum quantity prefilled in the quote form.', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '0.001', 'min' => '1'],
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_quote_button_text',
            'label' => __('Custom button text', 'polski'),
            'description' => __('Optional per-product override for the quote button label.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Catalog Mode Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Catalog Mode', 'polski') . '</h4>';

        woocommerce_wp_checkbox([
            'id' => '_polski_catalog_enabled',
            'label' => __('Enable catalog mode', 'polski'),
            'description' => __('Apply catalog mode to this product even if the module is limited to selected products.', 'polski'),
        ]);

        woocommerce_wp_checkbox([
            'id' => '_polski_catalog_hide_price',
            'label' => __('Hide price', 'polski'),
            'description' => __('Hide price display for this product when catalog mode is active.', 'polski'),
        ]);

        woocommerce_wp_checkbox([
            'id' => '_polski_catalog_hide_cart',
            'label' => __('Hide add to cart', 'polski'),
            'description' => __('Disable direct purchase for this product when catalog mode is active.', 'polski'),
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_catalog_message',
            'label' => __('Catalog mode message', 'polski'),
            'description' => __('Optional product-specific message shown instead of the default catalog mode notice.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_catalog_cta_text',
            'label' => __('Catalog mode CTA text', 'polski'),
            'description' => __('Optional product-specific CTA label.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Frequently Bought Together Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Frequently Bought Together', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_fbt_product_ids',
            'label' => __('Powiązane ID produktów', 'polski'),
            'description' => __('Podaj ID produktów oddzielone przecinkami. Kolejność będzie zachowana w sekcji zestawu.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_fbt_title',
            'label' => __('Własny nagłówek zestawu', 'polski'),
            'description' => __('Opcjonalny nagłówek tylko dla tego produktu.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_fbt_intro',
            'label' => __('Własny opis zestawu', 'polski'),
            'description' => __('Opcjonalny opis sekcji tylko dla tego produktu.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_fbt_button_text',
            'label' => __('Własny tekst przycisku', 'polski'),
            'description' => __('Opcjonalne nadpisanie tekstu przycisku dla tego produktu.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Badge Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Badge Management', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_badge_text',
            'label' => __('Główny badge', 'polski'),
            'description' => __('Ręczny badge wyświetlany niezależnie od automatycznych warunków.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_select([
            'id' => '_polski_badge_style',
            'label' => __('Styl badge', 'polski'),
            'options' => [
                '' => __('Domyślny', 'polski'),
                'accent' => __('Accent', 'polski'),
                'success' => __('Success', 'polski'),
                'warning' => __('Warning', 'polski'),
                'neutral' => __('Neutral', 'polski'),
            ],
            'description' => __('Styl ręcznego badge.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_badge_secondary_text',
            'label' => __('Drugi badge', 'polski'),
            'description' => __('Opcjonalny drugi badge, np. Polska marka, Eko, Hit tygodnia.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Tab Manager Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Tab Manager', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_tab_1_title',
            'label' => __('Tytuł zakładki 1', 'polski'),
            'description' => __('Opcjonalna dodatkowa zakładka dla tego produktu.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_tab_1_content',
            'label' => __('Treść zakładki 1', 'polski'),
            'description' => __('Treść HTML/tekst dla pierwszej dodatkowej zakładki.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_tab_2_title',
            'label' => __('Tytuł zakładki 2', 'polski'),
            'description' => __('Druga opcjonalna zakładka produktowa.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_tab_2_content',
            'label' => __('Treść zakładki 2', 'polski'),
            'description' => __('Treść HTML/tekst dla drugiej dodatkowej zakładki.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Featured Video Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Featured Video', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_featured_video_url',
            'label' => __('URL wideo', 'polski'),
            'description' => __('Obsługuje YouTube, Vimeo oraz bezpośredni link do pliku MP4.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_featured_video_title',
            'label' => __('Nagłówek sekcji wideo', 'polski'),
            'description' => __('Opcjonalny nagłówek tylko dla tego produktu.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Pre-Order Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Pre-Order', 'polski') . '</h4>';

        woocommerce_wp_checkbox([
            'id' => '_polski_preorder_enabled',
            'label' => __('Włącz przedsprzedaż', 'polski'),
            'description' => __('Produkt będzie dostępny do zakupu przed fizyczną dostępnością.', 'polski'),
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_preorder_date',
            'label' => __('Data wysyłki / premiery', 'polski'),
            'description' => __('Format YYYY-MM-DD.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_preorder_button_text',
            'label' => __('Własny tekst przycisku', 'polski'),
            'description' => __('Opcjonalne nadpisanie tekstu przycisku przedsprzedaży.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Product Add-Ons Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Product Add-Ons', 'polski') . '</h4>';

        woocommerce_wp_textarea_input([
            'id' => '_polski_addons_config',
            'label' => __('Konfiguracja dodatków', 'polski'),
            'description' => __('Jedna linia na dodatek: type|label|price|required|options|description|placeholder|max_length. Typy: checkbox, select, text, textarea. Użyj tego dla opcji takich jak grawer, pakowanie na prezent, montaż, wniesienie, rozszerzona gwarancja albo pakiet serwisowy. Dla select opcje oddziel średnikiem, np. Standard=0;Premium=49. Przykład checkbox: checkbox|Przedłużona gwarancja|29|no||Dodatkowe 12 miesięcy ochrony||. Przykład text: text|Treść graweru|15|no||Maksymalnie 40 znaków|Np. Dla Ani|40. Przykład textarea: textarea|Uwagi dla montażysty|0|no||Przekaż szczegóły ekipy lub miejsca montażu|Np. wejście od zaplecza|180.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Product Bundles Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Product Bundles', 'polski') . '</h4>';

        woocommerce_wp_textarea_input([
            'id' => '_polski_bundle_items',
            'label' => __('Elementy zestawu', 'polski'),
            'description' => __('Jedna linia na produkt: product_id|qty|required. Przykład: 123|1|yes. Produkt główny jest dodawany automatycznie.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_bundle_title',
            'label' => __('Własny nagłówek zestawu', 'polski'),
            'description' => __('Opcjonalny nagłówek sekcji tylko dla tego produktu.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_select([
            'id' => '_polski_bundle_discount_type',
            'label' => __('Typ rabatu', 'polski'),
            'options' => [
                'none' => __('Brak', 'polski'),
                'fixed' => __('Kwotowy', 'polski'),
                'percent' => __('Procentowy', 'polski'),
            ],
            'description' => __('Rabatuj cały pakiet zamiast pojedynczych pozycji.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_bundle_discount_value',
            'label' => __('Wartość rabatu', 'polski'),
            'description' => __('Kwota lub procent, zależnie od wybranego typu rabatu.', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_bundle_button_text',
            'label' => __('Własny tekst przycisku', 'polski'),
            'description' => __('Opcjonalne nadpisanie tekstu przycisku dla zestawu.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Gift Card Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Gift Cards', 'polski') . '</h4>';

        woocommerce_wp_checkbox([
            'id' => '_polski_gift_card_enabled',
            'label' => __('Produkt jest kartą podarunkową', 'polski'),
            'description' => __('Włącza formularz odbiorcy, generowanie kodu i realizację salda po zakupie.', 'polski'),
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_gift_card_amounts',
            'label' => __('Dostępne kwoty', 'polski'),
            'description' => __('Lista kwot oddzielonych przecinkami, np. 50,100,200.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_checkbox([
            'id' => '_polski_gift_card_allow_custom_amount',
            'label' => __('Pozwól wpisać własną kwotę', 'polski'),
            'description' => __('Klient sam określi wartość karty w dozwolonym zakresie.', 'polski'),
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_gift_card_min_amount',
            'label' => __('Minimalna kwota', 'polski'),
            'description' => __('Minimalna wartość własnej kwoty.', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_gift_card_max_amount',
            'label' => __('Maksymalna kwota', 'polski'),
            'description' => __('Maksymalna wartość własnej kwoty.', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        ]);

        echo '</div>';

        // --- Subscription Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Subscriptions', 'polski') . '</h4>';

        woocommerce_wp_checkbox([
            'id' => '_polski_subscription_enabled',
            'label' => __('Produkt subskrypcyjny', 'polski'),
            'description' => __('Zamówienie będzie tworzyć aktywną subskrypcję z odnowieniami.', 'polski'),
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_subscription_interval',
            'label' => __('Interwał', 'polski'),
            'description' => __('Co ile jednostek ma odnawiać się subskrypcja.', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1'],
        ]);

        woocommerce_wp_select([
            'id' => '_polski_subscription_period',
            'label' => __('Okres', 'polski'),
            'options' => [
                'day' => __('Dzień', 'polski'),
                'week' => __('Tydzień', 'polski'),
                'month' => __('Miesiąc', 'polski'),
                'year' => __('Rok', 'polski'),
            ],
            'description' => __('Jednostka czasu dla cyklu rozliczenia.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_subscription_length',
            'label' => __('Liczba odnowień', 'polski'),
            'description' => __('0 oznacza bezterminowo, każda inna liczba to maksymalna liczba cykli.', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '0'],
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_subscription_signup_fee',
            'label' => __('Opłata startowa', 'polski'),
            'description' => __('Jednorazowa opłata doliczana do pierwszego zamówienia.', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_subscription_trial_days',
            'label' => __('Okres próbny (dni)', 'polski'),
            'description' => __('0 oznacza brak okresu próbnego.', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '0'],
        ]);

        echo '</div>';

        echo '</div>';
    }

    /**
     * Save Polski product meta fields.
     */
    public function saveProductMeta(int $productId): void
    {
        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product) {
            return;
        }

        $fields = [
            '_polski_unit_price_product_amount' => 'float',
            '_polski_unit_price_base' => 'float',
            '_polski_unit_price_unit' => 'string',
            '_polski_delivery_time_id' => 'string',
            '_polski_withdrawal_exempt' => 'checkbox',
            '_polski_quote_enabled' => 'checkbox',
            '_polski_quote_only' => 'checkbox',
            '_polski_quote_min_qty' => 'float',
            '_polski_quote_button_text' => 'string',
            '_polski_catalog_enabled' => 'checkbox',
            '_polski_catalog_hide_price' => 'checkbox',
            '_polski_catalog_hide_cart' => 'checkbox',
            '_polski_catalog_message' => 'string',
            '_polski_catalog_cta_text' => 'string',
            '_polski_fbt_product_ids' => 'string',
            '_polski_fbt_title' => 'string',
            '_polski_fbt_intro' => 'textarea',
            '_polski_fbt_button_text' => 'string',
            '_polski_badge_text' => 'string',
            '_polski_badge_style' => 'string',
            '_polski_badge_secondary_text' => 'string',
            '_polski_tab_1_title' => 'string',
            '_polski_tab_1_content' => 'textarea',
            '_polski_tab_2_title' => 'string',
            '_polski_tab_2_content' => 'textarea',
            '_polski_featured_video_url' => 'string',
            '_polski_featured_video_title' => 'string',
            '_polski_preorder_enabled' => 'checkbox',
            '_polski_preorder_date' => 'string',
            '_polski_preorder_button_text' => 'string',
            '_polski_addons_config' => 'textarea',
            '_polski_bundle_items' => 'textarea',
            '_polski_bundle_title' => 'string',
            '_polski_bundle_discount_type' => 'string',
            '_polski_bundle_discount_value' => 'float',
            '_polski_bundle_button_text' => 'string',
            '_polski_gift_card_enabled' => 'checkbox',
            '_polski_gift_card_amounts' => 'string',
            '_polski_gift_card_allow_custom_amount' => 'checkbox',
            '_polski_gift_card_min_amount' => 'float',
            '_polski_gift_card_max_amount' => 'float',
            '_polski_subscription_enabled' => 'checkbox',
            '_polski_subscription_interval' => 'float',
            '_polski_subscription_period' => 'string',
            '_polski_subscription_length' => 'float',
            '_polski_subscription_signup_fee' => 'float',
            '_polski_subscription_trial_days' => 'float',
        ];

        foreach ($fields as $key => $type) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce.
            $value = $_POST[$key] ?? '';

            $sanitized = match ($type) {
                'float' => (string) (float) $value,
                'textarea' => sanitize_textarea_field((string) $value),
                'string' => sanitize_text_field((string) $value),
                'checkbox' => ($value === 'yes') ? 'yes' : 'no',
            };

            $product->update_meta_data($key, $sanitized);
        }

        $product->save_meta_data();
    }

    /**
     * Render Polski fields for variable product variations.
     *
     * @param int                  $loop
     * @param array<string, mixed> $variationData
     * @param \WP_Post             $variation
     */
    public function renderVariationFields(int $loop, array $variationData, \WP_Post $variation): void
    {
        $variationId = $variation->ID;

        echo '<div class="polski-variation-fields">';
        echo '<p class="form-row form-row-full"><strong>' . esc_html__('Polski', 'polski') . '</strong></p>';

        woocommerce_wp_text_input([
            'id' => "_polski_unit_price_product_amount_{$loop}",
            'name' => "_polski_variation_unit_price_product_amount[{$loop}]",
            'label' => __('Ilość produktu', 'polski'),
            'value' => get_post_meta($variationId, '_polski_unit_price_product_amount', true),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            'wrapper_class' => 'form-row form-row-first',
        ]);

        woocommerce_wp_text_input([
            'id' => "_polski_unit_price_base_{$loop}",
            'name' => "_polski_variation_unit_price_base[{$loop}]",
            'label' => __('Ilość bazowa', 'polski'),
            'value' => get_post_meta($variationId, '_polski_unit_price_base', true),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            'wrapper_class' => 'form-row form-row-last',
        ]);

        echo '</div>';
    }

    /**
     * Save Polski variation meta.
     */
    public function saveVariationMeta(int $variationId, int $loop): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce.
        $productAmount = $_POST['_polski_variation_unit_price_product_amount'][$loop] ?? '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $baseAmount = $_POST['_polski_variation_unit_price_base'][$loop] ?? '';

        update_post_meta($variationId, '_polski_unit_price_product_amount', (string) (float) $productAmount);
        update_post_meta($variationId, '_polski_unit_price_base', (string) (float) $baseAmount);
    }
}
