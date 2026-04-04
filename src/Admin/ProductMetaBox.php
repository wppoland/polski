<?php

declare(strict_types=1);
namespace Polski\Admin;

defined('ABSPATH') || exit;

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
            'description' => __('Podstawowa ilość referencyjna (np. 1 dla "na 1 kg", 100 dla "na 100 ml").', 'polski'),
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

        // --- GPSR Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('GPSR – Bezpieczeństwo produktu', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_manufacturer_name',
            'label' => __('Nazwa producenta', 'polski'),
            'description' => __('Pełna nazwa producenta wymagana przez GPSR.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_gpsr_manufacturer_address',
            'label' => __('Adres producenta', 'polski'),
            'description' => __('Pełny adres pocztowy producenta.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_importer_name',
            'label' => __('Nazwa importera', 'polski'),
            'description' => __('Pełna nazwa importera (jeśli dotyczy).', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_gpsr_importer_address',
            'label' => __('Adres importera', 'polski'),
            'description' => __('Pełny adres pocztowy importera.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_responsible_person',
            'label' => __('Osoba odpowiedzialna', 'polski'),
            'description' => __('Osoba odpowiedzialna w UE za zgodność produktu z GPSR.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_product_identifier',
            'label' => __('Identyfikator produktu', 'polski'),
            'description' => __('Numer partii, numer seryjny lub inny identyfikator produktu.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_gpsr_safety_warnings',
            'label' => __('Ostrzeżenia bezpieczeństwa', 'polski'),
            'description' => __('Ostrzeżenia dotyczące bezpieczeństwa produktu.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_gpsr_instructions',
            'label' => __('Instrukcje bezpieczeństwa', 'polski'),
            'description' => __('Instrukcje dotyczące bezpiecznego użytkowania produktu.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Anti-greenwashing Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Twierdzenia ekologiczne (Anti-greenwashing)', 'polski') . '</h4>';

        woocommerce_wp_textarea_input([
            'id' => '_polski_green_claim_basis',
            'label' => __('Podstawa twierdzenia ekologicznego', 'polski'),
            'description' => __('Naukowa lub prawna podstawa twierdzenia ekologicznego (wymagana przez dyrektywę anty-greenwashingową).', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_green_claim_cert_url',
            'label' => __('Link do certyfikatu', 'polski'),
            'description' => __('URL do oficjalnego certyfikatu potwierdzającego twierdzenie ekologiczne.', 'polski'),
            'desc_tip' => true,
            'type' => 'url',
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_green_claim_expiry',
            'label' => __('Data ważności certyfikatu (YYYY-MM-DD)', 'polski'),
            'description' => __('Data wygaśnięcia certyfikatu ekologicznego w formacie YYYY-MM-DD.', 'polski'),
            'desc_tip' => true,
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
            '_polski_badge_text' => 'string',
            '_polski_badge_style' => 'string',
            '_polski_badge_secondary_text' => 'string',
            '_polski_tab_1_title' => 'string',
            '_polski_tab_1_content' => 'textarea',
            '_polski_tab_2_title' => 'string',
            '_polski_tab_2_content' => 'textarea',
            '_polski_featured_video_url' => 'string',
            '_polski_featured_video_title' => 'string',
            '_polski_gpsr_manufacturer_name' => 'string',
            '_polski_gpsr_manufacturer_address' => 'textarea',
            '_polski_gpsr_importer_name' => 'string',
            '_polski_gpsr_importer_address' => 'textarea',
            '_polski_gpsr_responsible_person' => 'string',
            '_polski_gpsr_product_identifier' => 'string',
            '_polski_gpsr_safety_warnings' => 'textarea',
            '_polski_gpsr_instructions' => 'textarea',
            '_polski_green_claim_basis' => 'textarea',
            '_polski_green_claim_cert_url' => 'url',
            '_polski_green_claim_expiry' => 'string',
        ];

        foreach ($fields as $key => $type) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce.
            $value = $_POST[$key] ?? '';

            $sanitized = match ($type) {
                'float' => (string) (float) $value,
                'textarea' => sanitize_textarea_field((string) $value),
                'url' => esc_url_raw((string) $value),
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
