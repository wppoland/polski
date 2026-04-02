<?php

declare(strict_types=1);

namespace Spolszczony\Admin;

use Spolszczony\Contract\HasHooks;

/**
 * Extends WooCommerce product CSV import/export with Spolszczony fields.
 */
final class CSVImportExport implements HasHooks
{
    /** @var array<string, string> */
    private const COLUMN_MAP = [
        'spolszczony_unit_price_base' => '_spolszczony_unit_price_base',
        'spolszczony_unit_price_unit' => '_spolszczony_unit_price_unit',
        'spolszczony_unit_price_amount' => '_spolszczony_unit_price_product_amount',
        'spolszczony_delivery_time' => '_spolszczony_delivery_time_id',
        'spolszczony_gpsr_responsible' => '_spolszczony_gpsr_responsible',
        'spolszczony_power_supply' => '_spolszczony_power_supply',
        'spolszczony_defect_description' => '_spolszczony_defect_description',
        'spolszczony_withdrawal_exempt' => '_spolszczony_withdrawal_exempt',
        'spolszczony_ingredients' => '_spolszczony_ingredients',
        'spolszczony_nutri_score' => '_spolszczony_nutri_score',
        'spolszczony_alcohol_content' => '_spolszczony_alcohol_content',
        'spolszczony_place_of_origin' => '_spolszczony_place_of_origin',
        'spolszczony_net_filling' => '_spolszczony_net_filling_quantity',
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
        $columns['spolszczony_unit_price_base'] = __('Unit price base', 'spolszczony');
        $columns['spolszczony_unit_price_unit'] = __('Unit price unit', 'spolszczony');
        $columns['spolszczony_unit_price_amount'] = __('Product amount', 'spolszczony');
        $columns['spolszczony_delivery_time'] = __('Delivery time ID', 'spolszczony');
        $columns['spolszczony_gpsr_responsible'] = __('GPSR responsible', 'spolszczony');
        $columns['spolszczony_power_supply'] = __('Power supply', 'spolszczony');
        $columns['spolszczony_defect_description'] = __('Defect description', 'spolszczony');
        $columns['spolszczony_withdrawal_exempt'] = __('Withdrawal exempt', 'spolszczony');
        $columns['spolszczony_ingredients'] = __('Ingredients', 'spolszczony');
        $columns['spolszczony_nutri_score'] = __('Nutri-Score', 'spolszczony');
        $columns['spolszczony_alcohol_content'] = __('Alcohol content', 'spolszczony');
        $columns['spolszczony_place_of_origin'] = __('Place of origin', 'spolszczony');
        $columns['spolszczony_net_filling'] = __('Net filling quantity', 'spolszczony');

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
            $label = str_replace('spolszczony_', 'Spolszczony: ', $csvKey);
            $columns[$label] = $csvKey;
        }

        return $columns;
    }

    /**
     * Process imported Spolszczony fields.
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
