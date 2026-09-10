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
        echo '<h4 style="padding-left:12px;">' . esc_html__('Unit price', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_unit_price_product_amount',
            'label' => __('Product quantity', 'polski'),
            'description' => __('Product quantity in package (e.g. 500 for 500g).', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_unit_price_base',
            'label' => __('Base quantity', 'polski'),
            'description' => __('Base reference quantity (e.g. 1 for "per 1 kg", 100 for "per 100 ml").', 'polski'),
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
            'label' => __('Unit', 'polski'),
            'options' => $unitOptions,
            'description' => __('Unit of measure for unit price display.', 'polski'),
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
            'label' => __('Delivery time', 'polski'),
            'options' => $dtOptions,
            'description' => __('Estimated delivery time displayed on the product page.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Withdrawal Section ---
        echo '<div class="options_group">';

        woocommerce_wp_checkbox([
            'id' => '_polski_withdrawal_exempt',
            'label' => __('Right of withdrawal exemption', 'polski'),
            'description' => __('This product is exempt from the 14-day right of withdrawal (e.g. digital content, perishable goods).', 'polski'),
        ]);

        echo '</div>';

        // --- Invoicing Section ---
        // The key is spelled out rather than taken from Gtu::META so the release
        // check that pairs every rendered input with its entry in the save map
        // can see it. Both spellings have to stay in step.
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Invoicing', 'polski') . '</h4>';

        woocommerce_wp_select([
            'id' => '_polski_gtu_code',
            'label' => __('GTU marking', 'polski'),
            'options' => \Polski\Invoice\Gtu::options(),
            'description' => __('Goods and services group under JPK_V7. Set it only where the product really belongs to one of the thirteen groups; the marking is then printed against this line on the invoice.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Badge Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Badge Management', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_badge_text',
            'label' => __('Main badge', 'polski'),
            'description' => __('Manual badge displayed independently of automatic conditions.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_select([
            'id' => '_polski_badge_style',
            'label' => __('Badge style', 'polski'),
            'options' => [
                '' => __('Default', 'polski'),
                'accent' => __('Accent', 'polski'),
                'success' => __('Success', 'polski'),
                'warning' => __('Warning', 'polski'),
                'neutral' => __('Neutral', 'polski'),
            ],
            'description' => __('Manual badge style.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_badge_secondary_text',
            'label' => __('Secondary badge', 'polski'),
            'description' => __('Optional second badge, e.g. Polish brand, Eco, Sale hit.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Tab Manager Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Tab Manager', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_tab_1_title',
            'label' => __('Tab 1 title', 'polski'),
            'description' => __('Optional additional tab for this product.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_tab_1_content',
            'label' => __('Tab 1 content', 'polski'),
            'description' => __('HTML/text content for the first additional tab.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_tab_2_title',
            'label' => __('Tab 2 title', 'polski'),
            'description' => __('Second optional product tab.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_tab_2_content',
            'label' => __('Tab 2 content', 'polski'),
            'description' => __('HTML/text content for the second additional tab.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Featured Video Section ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Featured Video', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_featured_video_url',
            'label' => __('Video URL', 'polski'),
            'description' => __('Supports YouTube, Vimeo, and direct MP4 file links.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_featured_video_title',
            'label' => __('Video section heading', 'polski'),
            'description' => __('Optional heading only for this product.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Product responsibility -------------------------------------
        // GPSR art. 19(1)(a) wants a postal AND an electronic address for the
        // economic operator, so every party here carries a contact field.
        // Kept apart from the safety texts below: one block is about who is
        // answerable for the product, the other about how to use it safely.
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Product responsibility', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_manufacturer_name',
            'label' => __('Manufacturer name', 'polski'),
            'description' => __('Full manufacturer name required by GPSR.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_gpsr_manufacturer_address',
            'label' => __('Manufacturer address', 'polski'),
            'description' => __('Full postal address of the manufacturer.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_manufacturer_contact',
            'label' => __('Manufacturer contact', 'polski'),
            'description' => __('Electronic address, an email or a web page, that GPSR requires alongside the postal one.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_responsible_person',
            'label' => __('Responsible person', 'polski'),
            'description' => __('Person responsible in the EU for product compliance with GPSR.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_gpsr_responsible_address',
            'label' => __('Responsible person address', 'polski'),
            'description' => __('Full postal address of the EU responsible person.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_responsible_contact',
            'label' => __('Responsible person contact', 'polski'),
            'description' => __('Electronic address, an email or a web page, for the EU responsible person.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_importer_name',
            'label' => __('Importer name', 'polski'),
            'description' => __('Full importer name (if applicable).', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_gpsr_importer_address',
            'label' => __('Importer address', 'polski'),
            'description' => __('Full postal address of the importer.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_importer_contact',
            'label' => __('Importer contact', 'polski'),
            'description' => __('Electronic address, an email or a web page, for the importer.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Product safety (GPSR) --------------------------------------
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Product safety (GPSR)', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_gpsr_product_identifier',
            'label' => __('Product identifier', 'polski'),
            'description' => __('Batch number, serial number, or other product identifier.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_gpsr_safety_warnings',
            'label' => __('Safety warnings', 'polski'),
            'description' => __('Safety warnings regarding the product.', 'polski'),
            'desc_tip' => true,
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_gpsr_instructions',
            'label' => __('Safety instructions', 'polski'),
            'description' => __('Instructions for safe use of the product.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Consumer information Section (Directive 2024/825) ---
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Consumer information (2024/825)', 'polski') . '</h4>';

        woocommerce_wp_text_input([
            'id' => '_polski_durability_guarantee_months',
            'label' => __('Guarantee of durability (months)', 'polski'),
            'description' => __('Only where the producer offers a commercial guarantee of durability. Leave empty when there is none; the statutory guarantee is announced separately in the module settings.', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['min' => '0', 'step' => '1'],
        ]);

        woocommerce_wp_text_input([
            'id' => '_polski_update_period_months',
            'label' => __('Free software updates (months)', 'polski'),
            'description' => __('For goods with digital elements: how long free updates are supplied.', 'polski'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['min' => '0', 'step' => '1'],
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_polski_repair_info',
            'label' => __('Repair information', 'polski'),
            'description' => __('Repairability score where one is established, otherwise spare part availability and repair contact.', 'polski'),
            'desc_tip' => true,
        ]);

        echo '</div>';

        // --- Anti-greenwashing Section ---
        // Guarded together with the matching skip in saveProductMeta. Hiding
        // the fields without that skip would blank the stored claims on the
        // next product save, because the save loop treats an absent POST key as
        // an empty value.
        if (ModulesPage::isModuleEnabled('green_claims')) {
            echo '<div class="options_group">';
            echo '<h4 style="padding-left:12px;">' . esc_html__('Environmental claims (Anti-greenwashing)', 'polski') . '</h4>';

            woocommerce_wp_textarea_input([
                'id' => '_polski_green_claim_basis',
                'label' => __('Environmental claim basis', 'polski'),
                'description' => __('Scientific or legal basis for the environmental claim (required by the anti-greenwashing directive).', 'polski'),
                'desc_tip' => true,
            ]);

            woocommerce_wp_text_input([
                'id' => '_polski_green_claim_cert_url',
                'label' => __('Certificate link', 'polski'),
                'description' => __('URL to the official certificate supporting the environmental claim.', 'polski'),
                'desc_tip' => true,
                'type' => 'url',
            ]);

            woocommerce_wp_text_input([
                'id' => '_polski_green_claim_expiry',
                'label' => __('Certificate expiry date (YYYY-MM-DD)', 'polski'),
                'description' => __('Environmental certificate expiry date in YYYY-MM-DD format.', 'polski'),
                'desc_tip' => true,
            ]);

            echo '</div>';
        }

        if (ModulesPage::isModuleEnabled('vat_margin')) {
            echo '<div class="options_group">';
            woocommerce_wp_select([
                'id' => \Polski\Service\VatMarginService::META_SCHEME,
                'label' => __('VAT margin scheme', 'polski'),
                'description' => __('Taxed on the margin under art. 120 ustawy o VAT. The invoice will name the scheme and show no VAT for these goods.', 'polski'),
                'desc_tip' => true,
                'options' => \Polski\Service\VatMarginService::schemes(),
            ]);
            echo '</div>';
        }

        if (ModulesPage::isModuleEnabled('deposit')) {
            echo '<div class="options_group">';
            echo '<h4 style="padding-left:12px;">' . esc_html__('Deposit scheme (system kaucyjny)', 'polski') . '</h4>';

            woocommerce_wp_select([
                'id' => \Polski\Service\DepositService::META_TYPE,
                'label' => __('Packaging', 'polski'),
                'description' => __('Packaging covered by the deposit return scheme. The deposit is added on top of the price at checkout and is refunded when the packaging is returned.', 'polski'),
                'desc_tip' => true,
                'options' => \Polski\Service\DepositService::types(),
            ]);

            woocommerce_wp_text_input([
                'id' => \Polski\Service\DepositService::META_UNITS,
                'label' => __('Units of packaging', 'polski'),
                'description' => __('How many covered containers one item contains. A six-pack is 6. Leave at 1 for a single bottle or can.', 'polski'),
                'desc_tip' => true,
                'type' => 'number',
                'custom_attributes' => ['min' => '1', 'step' => '1'],
            ]);

            echo '</div>';
        }

        if (ModulesPage::isModuleEnabled('food_module')) {
            echo '<div class="options_group">';
            echo '<h4 style="padding-left:12px;">' . esc_html__('Food and supplements', 'polski') . '</h4>';

            woocommerce_wp_textarea_input([
                'id' => '_polski_ingredients',
                'label' => __('Ingredients', 'polski'),
                'description' => __('Ingredient list in descending order by weight, with allergens emphasised, as Regulation (EU) 1169/2011 Annex VII requires. Allergens themselves are the separate Allergens taxonomy.', 'polski'),
                'desc_tip' => true,
            ]);

            woocommerce_wp_select([
                'id' => '_polski_nutri_score',
                'label' => __('Nutri-Score', 'polski'),
                'description' => __('Voluntary front-of-pack grade. Leave unset unless you have calculated it.', 'polski'),
                'desc_tip' => true,
                'options' => [
                    '' => __('Not set', 'polski'),
                    'A' => 'A',
                    'B' => 'B',
                    'C' => 'C',
                    'D' => 'D',
                    'E' => 'E',
                ],
            ]);

            woocommerce_wp_text_input([
                'id' => '_polski_nutrient_reference_unit',
                'label' => __('Nutrition reference', 'polski'),
                'description' => __('What the values below are per. Empty uses the store default, normally 100 g.', 'polski'),
                'desc_tip' => true,
                'placeholder' => __('100 g', 'polski'),
            ]);

            // One input per canonical nutrient rather than the pipe-separated
            // string the CSV importer takes. Same nine slugs, so an export
            // still round-trips; the merchant just does not have to learn the
            // syntax to fill in a label they are legally required to print.
            foreach (\Polski\Service\FoodService::nutrientLabels() as $slug => $label) {
                $unit = \Polski\Service\FoodService::NUTRIENT_UNITS[$slug] ?? '';
                woocommerce_wp_text_input([
                    'id' => 'polski_nutrient_' . $slug,
                    'name' => 'polski_nutrient[' . $slug . ']',
                    'label' => $label,
                    'type' => 'number',
                    'value' => self::nutrientValue($productId, $slug),
                    'custom_attributes' => ['min' => '0', 'step' => 'any'],
                    'data_type' => '',
                    'description' => $unit,
                ]);
            }

            woocommerce_wp_text_input([
                'id' => '_polski_net_filling_quantity',
                'label' => __('Net quantity', 'polski'),
                'description' => __('Net quantity as it appears on the pack, for example 500 g or 0.75 l.', 'polski'),
                'desc_tip' => true,
            ]);

            woocommerce_wp_text_input([
                'id' => '_polski_alcohol_content',
                'label' => __('Alcohol by volume', 'polski'),
                'description' => __('Required above 1.2% vol. Enter the number only, for example 12.5.', 'polski'),
                'desc_tip' => true,
            ]);

            woocommerce_wp_text_input([
                'id' => '_polski_place_of_origin',
                'label' => __('Country of origin', 'polski'),
                'description' => __('Country of origin or place of provenance, where it is required or where its absence could mislead.', 'polski'),
                'desc_tip' => true,
            ]);

            woocommerce_wp_text_input([
                'id' => '_polski_food_distributor',
                'label' => __('Food business operator', 'polski'),
                'description' => __('Name and address of the operator under whose name the food is marketed.', 'polski'),
                'desc_tip' => true,
            ]);

            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Meta keys whose input only renders when their module is on.
     *
     * The save loop below reads an absent POST key as an empty value, which is
     * right for a checkbox the user unticked and wrong for a field that was
     * never on the page. These keys are skipped instead of blanked.
     *
     * @var array<string, string>
     */
    private const MODULE_GATED_FIELDS = [
        '_polski_green_claim_basis' => 'green_claims',
        '_polski_green_claim_cert_url' => 'green_claims',
        '_polski_green_claim_expiry' => 'green_claims',
        \Polski\Service\DepositService::META_TYPE => 'deposit',
        \Polski\Service\DepositService::META_UNITS => 'deposit',
        \Polski\Service\VatMarginService::META_SCHEME => 'vat_margin',
        '_polski_ingredients' => 'food_module',
        '_polski_nutri_score' => 'food_module',
        '_polski_nutrient_reference_unit' => 'food_module',
        '_polski_net_filling_quantity' => 'food_module',
        '_polski_alcohol_content' => 'food_module',
        '_polski_place_of_origin' => 'food_module',
        '_polski_food_distributor' => 'food_module',
    ];

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
            '_polski_gtu_code' => 'string',
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
            '_polski_gpsr_manufacturer_contact' => 'string',
            '_polski_gpsr_importer_name' => 'string',
            '_polski_gpsr_importer_address' => 'textarea',
            '_polski_gpsr_importer_contact' => 'string',
            '_polski_gpsr_responsible_person' => 'string',
            '_polski_gpsr_responsible_address' => 'textarea',
            '_polski_gpsr_responsible_contact' => 'string',
            '_polski_gpsr_product_identifier' => 'string',
            '_polski_gpsr_safety_warnings' => 'textarea',
            '_polski_gpsr_instructions' => 'textarea',
            '_polski_durability_guarantee_months' => 'int',
            '_polski_update_period_months' => 'int',
            '_polski_repair_info' => 'textarea',
            '_polski_green_claim_basis' => 'textarea',
            '_polski_green_claim_cert_url' => 'url',
            '_polski_green_claim_expiry' => 'string',
            \Polski\Service\DepositService::META_TYPE => 'string',
            \Polski\Service\DepositService::META_UNITS => 'int',
            \Polski\Service\VatMarginService::META_SCHEME => 'string',
            '_polski_ingredients' => 'textarea',
            '_polski_nutri_score' => 'string',
            '_polski_nutrient_reference_unit' => 'string',
            '_polski_net_filling_quantity' => 'string',
            '_polski_alcohol_content' => 'string',
            '_polski_place_of_origin' => 'string',
            '_polski_food_distributor' => 'string',
        ];

        foreach ($fields as $key => $type) {
            // A field whose module is off was never rendered, so there is no
            // POST value and no user intent. Leave the stored value alone
            // rather than reading the absence as "cleared".
            if (isset(self::MODULE_GATED_FIELDS[$key])
                && ! ModulesPage::isModuleEnabled(self::MODULE_GATED_FIELDS[$key])) {
                continue;
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies nonce/capability before this hook fires.
            if (! isset($_POST[$key])) {
                $sanitized = $type === 'checkbox' ? 'no' : '';
            } else {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $raw = (string) wp_unslash($_POST[$key]);

                // Do NOT pre-sanitise with sanitize_text_field: it folds every
                // newline into a space, so a multi-line GPSR address or a list
                // of safety warnings arrived at sanitize_textarea_field already
                // flattened and was stored as one line. Each type gets the
                // sanitiser that suits it, and nothing else.
                $sanitized = match ($type) {
                    'float' => (string) (float) sanitize_text_field($raw),
                    // Months are never negative; a pasted "-6" would otherwise
                    // render as a negative guarantee period on the storefront.
                    'int' => (string) max(0, (int) sanitize_text_field($raw)),
                    'textarea' => sanitize_textarea_field($raw),
                    'url' => esc_url_raw(sanitize_text_field($raw)),
                    'string' => sanitize_text_field($raw),
                    'checkbox' => sanitize_text_field($raw) === 'yes' ? 'yes' : 'no',
                };
            }

            $product->update_meta_data($key, $sanitized);
        }

        $this->saveNutrients($product);

        $product->save_meta_data();
    }

    /**
     * Fold the per-nutrient inputs back into the canonical JSON.
     *
     * The nine inputs are assembled into the pipe form the CSV importer takes
     * and handed to the same parser, so a value typed here and a value
     * imported from a spreadsheet cannot end up shaped differently.
     */
    private function saveNutrients(\WC_Product $product): void
    {
        if (! ModulesPage::isModuleEnabled('food_module')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies nonce/capability before this hook fires.
        $posted = isset($_POST['polski_nutrient']) && is_array($_POST['polski_nutrient'])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            ? wp_unslash($_POST['polski_nutrient'])
            : [];

        $pairs = [];

        foreach (\Polski\Service\FoodService::NUTRIENT_UNITS as $slug => $unit) {
            $value = isset($posted[$slug]) ? trim(sanitize_text_field((string) $posted[$slug])) : '';

            if ($value === '' || ! is_numeric($value)) {
                continue;
            }

            $pairs[] = $slug . ':' . $value;
        }

        $product->update_meta_data(
            '_polski_nutrients',
            \Polski\Service\FoodService::parseNutrientsCsv(implode('|', $pairs)),
        );
    }

    /**
     * Current value of one nutrient, for the input above.
     */
    private static function nutrientValue(int $productId, string $slug): string
    {
        $stored = get_post_meta($productId, '_polski_nutrients', true);

        if (is_string($stored) && $stored !== '') {
            $stored = json_decode($stored, true);
        }

        if (! is_array($stored) || ! isset($stored[$slug]['value'])) {
            return '';
        }

        return (string) $stored[$slug]['value'];
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
            'label' => __('Product quantity', 'polski'),
            'value' => get_post_meta($variationId, '_polski_unit_price_product_amount', true),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            'wrapper_class' => 'form-row form-row-first',
        ]);

        woocommerce_wp_text_input([
            'id' => "_polski_unit_price_base_{$loop}",
            'name' => "_polski_variation_unit_price_base[{$loop}]",
            'label' => __('Base quantity', 'polski'),
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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies nonce/capability before this hook fires.
        $productAmount = isset($_POST['_polski_variation_unit_price_product_amount'][$loop])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            ? sanitize_text_field((string) wp_unslash($_POST['_polski_variation_unit_price_product_amount'][$loop]))
            : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $baseAmount = isset($_POST['_polski_variation_unit_price_base'][$loop])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            ? sanitize_text_field((string) wp_unslash($_POST['_polski_variation_unit_price_base'][$loop]))
            : '';

        update_post_meta($variationId, '_polski_unit_price_product_amount', (string) (float) $productAmount);
        update_post_meta($variationId, '_polski_unit_price_base', (string) (float) $baseAmount);
    }
}
