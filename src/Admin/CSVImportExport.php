<?php

declare(strict_types=1);

namespace Polski\Admin;

use Polski\Contract\HasHooks;

/**
 * Extends WooCommerce product CSV import/export with Polski fields.
 */
final class CSVImportExport implements HasHooks
{
    /** @var array<string, string> */
    private const COLUMN_MAP = [
        'polski_unit_price_base' => '_polski_unit_price_base',
        'polski_unit_price_unit' => '_polski_unit_price_unit',
        'polski_unit_price_amount' => '_polski_unit_price_product_amount',
        'polski_delivery_time' => '_polski_delivery_time_id',
        'polski_gpsr_responsible' => '_polski_gpsr_responsible',
        'polski_power_supply' => '_polski_power_supply',
        'polski_defect_description' => '_polski_defect_description',
        'polski_withdrawal_exempt' => '_polski_withdrawal_exempt',
        'polski_ingredients' => '_polski_ingredients',
        'polski_nutri_score' => '_polski_nutri_score',
        'polski_alcohol_content' => '_polski_alcohol_content',
        'polski_place_of_origin' => '_polski_place_of_origin',
        'polski_net_filling' => '_polski_net_filling_quantity',
    ];

    public function registerHooks(): void
    {
        // Export.
        add_filter('woocommerce_product_export_column_names', [$this, 'addExportColumns']);
        add_filter('woocommerce_product_export_product_default_columns', [$this, 'addExportColumns']);

        foreach (self::COLUMN_MAP as $csvKey => $metaKey) {
            add_filter(
                'woocommerce_product_export_product_column_' . $csvKey,
                function ($value, $product) use ($metaKey) {
                    return $product->get_meta($metaKey, true);
                },
                10,
                2,
            );
        }

        // Import.
        add_filter('woocommerce_csv_product_import_mapping_options', [$this, 'addImportMappingOptions']);
        add_filter('woocommerce_csv_product_import_mapping_default_columns', [$this, 'addImportMappingDefaults']);
        add_filter('woocommerce_product_import_inserted_product_object', [$this, 'processImport'], 10, 2);
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addExportColumns(array $columns): array
    {
        $columns['polski_unit_price_base'] = __('Bazowa cena jednostkowa', 'polski');
        $columns['polski_unit_price_unit'] = __('Jednostka ceny jednostkowej', 'polski');
        $columns['polski_unit_price_amount'] = __('Ilość produktu', 'polski');
        $columns['polski_delivery_time'] = __('ID czasu dostawy', 'polski');
        $columns['polski_gpsr_responsible'] = __('Osoba odpowiedzialna (GPSR)', 'polski');
        $columns['polski_power_supply'] = __('Zasilanie', 'polski');
        $columns['polski_defect_description'] = __('Opis wady', 'polski');
        $columns['polski_withdrawal_exempt'] = __('Wyłączenie z prawa odstąpienia', 'polski');
        $columns['polski_ingredients'] = __('Składniki', 'polski');
        $columns['polski_nutri_score'] = __('Nutri-Score', 'polski');
        $columns['polski_alcohol_content'] = __('Zawartość alkoholu', 'polski');
        $columns['polski_place_of_origin'] = __('Kraj pochodzenia', 'polski');
        $columns['polski_net_filling'] = __('Ilość netto', 'polski');

        return $columns;
    }

    /**
     * @param array<string, string> $options
     * @return array<string, string>
     */
    public function addImportMappingOptions(array $options): array
    {
        return $this->addExportColumns($options);
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addImportMappingDefaults(array $columns): array
    {
        foreach (self::COLUMN_MAP as $csvKey => $metaKey) {
            $label = str_replace('polski_', 'Polski: ', $csvKey);
            $columns[$label] = $csvKey;
        }

        return $columns;
    }

    /**
     * Process imported Polski fields.
     *
     * @param \WC_Product          $product
     * @param array<string, mixed> $data
     */
    public function processImport(\WC_Product $product, array $data): \WC_Product
    {
        foreach (self::COLUMN_MAP as $csvKey => $metaKey) {
            if (isset($data[$csvKey]) && $data[$csvKey] !== '') {
                $product->update_meta_data($metaKey, sanitize_text_field((string) $data[$csvKey]));
            }
        }

        return $product;
    }
}
