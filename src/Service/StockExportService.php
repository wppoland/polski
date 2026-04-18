<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * WooCommerce stock and inventory CSV export.
 *
 * Exports product stock data as CSV with configurable fields:
 * ID, SKU, name, stock quantity, regular price, sale price, categories, status.
 * Supports filtering by managed stock and stock thresholds.
 */
final class StockExportService implements HasHooks
{
    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('stock_export')) {
            return;
        }

        add_action('admin_menu', [$this, 'addAdminPage']);
        add_action('admin_init', [$this, 'handleExport']);
    }

    public function addAdminPage(): void
    {
        add_submenu_page(
            'edit.php?post_type=product',
            __('Stock Export', 'polski'),
            __('Stock Export', 'polski'),
            'view_woocommerce_reports',
            'polski-stock-export',
            [$this, 'renderPage'],
        );
    }

    public function renderPage(): void
    {
        $fields = [
            'id' => __('Product ID', 'polski'),
            'sku' => __('SKU', 'polski'),
            'name' => __('Product name', 'polski'),
            'type' => __('Product type', 'polski'),
            'stock' => __('Stock quantity', 'polski'),
            'stock_status' => __('Stock status', 'polski'),
            'regular_price' => __('Regular price', 'polski'),
            'sale_price' => __('Sale price', 'polski'),
            'categories' => __('Categories', 'polski'),
            'weight' => __('Weight', 'polski'),
        ];

        $savedFields = get_option('polski_stock_export_fields', ['id', 'sku', 'name', 'stock', 'regular_price']);

        echo '<div class="wrap"><h1>' . esc_html__('Stock Export', 'polski') . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('polski_stock_export', '_polski_export_nonce');
        echo '<input type="hidden" name="action" value="polski_export_stock">';

        echo '<table class="form-table">';

        // Fields selection.
        echo '<tr><th scope="row">' . esc_html__('Export fields', 'polski') . '</th><td>';

        foreach ($fields as $key => $label) {
            printf(
                '<label style="display:block;margin-bottom:4px"><input type="checkbox" name="fields[]" value="%s" %s> %s</label>',
                esc_attr($key),
                checked(in_array($key, $savedFields, true), true, false),
                esc_html($label),
            );
        }

        echo '</td></tr>';

        // Filter: managed stock only.
        echo '<tr><th scope="row">' . esc_html__('Products', 'polski') . '</th><td>';
        echo '<label><input type="checkbox" name="managed_only" value="1"> ' . esc_html__('Only products with managed stock', 'polski') . '</label>';
        echo '</td></tr>';

        // Filter: stock threshold.
        echo '<tr><th scope="row">' . esc_html__('Stock filter', 'polski') . '</th><td>';
        echo '<select name="stock_compare"><option value="">' . esc_html__('No filter', 'polski') . '</option>';
        echo '<option value="lte">' . esc_html__('Stock <=', 'polski') . '</option>';
        echo '<option value="gte">' . esc_html__('Stock >=', 'polski') . '</option>';
        echo '<option value="eq">' . esc_html__('Stock =', 'polski') . '</option>';
        echo '</select> ';
        echo '<input type="number" name="stock_value" value="" min="0" style="width:80px">';
        echo '</td></tr>';

        // Include variations.
        echo '<tr><th scope="row">' . esc_html__('Variations', 'polski') . '</th><td>';
        echo '<label><input type="checkbox" name="include_variations" value="1" checked> ' . esc_html__('Include product variations', 'polski') . '</label>';
        echo '</td></tr>';

        echo '</table>';

        submit_button(__('Export CSV', 'polski'), 'primary', 'submit', true, ['style' => 'margin-right:8px']);
        submit_button(__('Preview on screen', 'polski'), 'secondary', 'preview', true);

        echo '</form></div>';
    }

    public function handleExport(): void
    {
        if (empty($_POST['action']) || $_POST['action'] !== 'polski_export_stock') {
            return;
        }

        if (! current_user_can('view_woocommerce_reports')) {
            return;
        }

        check_admin_referer('polski_stock_export', '_polski_export_nonce');

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Admin referer verified above.
        $fields = ['id', 'sku', 'name', 'stock'];
        if (isset($_POST['fields']) && is_array($_POST['fields'])) {
            $fields = [];
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each item is sanitized in the loop body.
            foreach (wp_unslash($_POST['fields']) as $value) {
                $fields[] = sanitize_key((string) $value);
            }
            $fields = array_values($fields);
        }
        $stockCompare = isset($_POST['stock_compare'])
            ? sanitize_key((string) wp_unslash($_POST['stock_compare']))
            : '';
        $stockValue = isset($_POST['stock_value'])
            ? absint(wp_unslash($_POST['stock_value']))
            : 0;
        $managedOnly = ! empty($_POST['managed_only']);
        $includeVariations = ! empty($_POST['include_variations']);
        $isPreview = isset($_POST['preview']);
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Save field selection.
        update_option('polski_stock_export_fields', $fields);

        $products = $this->getProducts($managedOnly, $includeVariations, $stockCompare, $stockValue);

        if ($isPreview) {
            $this->renderPreview($products, $fields);
            return;
        }

        $this->outputCsv($products, $fields);
    }

    /**
     * @return list<\WC_Product>
     */
    private function getProducts(bool $managedOnly, bool $includeVariations, string $stockCompare, int $stockValue): array
    {
        $args = [
            'status' => 'publish',
            'limit' => -1,
            'type' => ['simple', 'variable', 'external', 'grouped'],
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        if ($managedOnly) {
            $args['manage_stock'] = true;
        }

        $products = wc_get_products($args);
        $products = is_array($products) ? $products : [];
        $result = [];

        foreach ($products as $product) {
            // Stock filter.
            if ($stockCompare !== '' && $product->managing_stock()) {
                $stock = (int) $product->get_stock_quantity();
                $matches = match ($stockCompare) {
                    'lte' => $stock <= $stockValue,
                    'gte' => $stock >= $stockValue,
                    'eq' => $stock === $stockValue,
                    default => true,
                };

                if (! $matches) {
                    continue;
                }
            }

            $result[] = $product;

            // Include variations.
            if ($includeVariations && $product->is_type('variable')) {
                foreach ($product->get_children() as $childId) {
                    $variation = wc_get_product($childId);

                    if ($variation instanceof \WC_Product) {
                        if ($managedOnly && ! $variation->managing_stock()) {
                            continue;
                        }

                        if ($stockCompare !== '' && $variation->managing_stock()) {
                            $vStock = (int) $variation->get_stock_quantity();
                            $vMatches = match ($stockCompare) {
                                'lte' => $vStock <= $stockValue,
                                'gte' => $vStock >= $stockValue,
                                'eq' => $vStock === $stockValue,
                                default => true,
                            };

                            if (! $vMatches) {
                                continue;
                            }
                        }

                        $result[] = $variation;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param list<\WC_Product> $products
     * @param list<string>      $fields
     */
    private function outputCsv(array $products, array $fields): void
    {
        $filename = 'stock_export_' . wp_date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            exit;
        }

        // BOM for Excel.
        echo "\xEF\xBB\xBF";

        // Header row.
        $headers = $this->getFieldLabels($fields);
        fputcsv($output, $headers, ';');

        foreach ($products as $product) {
            $row = $this->buildRow($product, $fields);
            fputcsv($output, $row, ';');
        }

        exit;
    }

    /**
     * @param list<\WC_Product> $products
     * @param list<string>      $fields
     */
    private function renderPreview(array $products, array $fields): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('Stock Export Preview', 'polski') . '</h1>';
        printf('<p>%s: <strong>%d</strong></p>', esc_html__('Products found', 'polski'), count($products));
        echo '<table class="widefat fixed striped"><thead><tr>';

        foreach ($this->getFieldLabels($fields) as $label) {
            printf('<th>%s</th>', esc_html($label));
        }

        echo '</tr></thead><tbody>';

        foreach (array_slice($products, 0, 500) as $product) {
            echo '<tr>';

            foreach ($this->buildRow($product, $fields) as $value) {
                printf('<td>%s</td>', esc_html($value));
            }

            echo '</tr>';
        }

        echo '</tbody></table>';

        if (count($products) > 500) {
            printf('<p><em>%s</em></p>', esc_html__('Preview limited to 500 rows. Use CSV export for full data.', 'polski'));
        }

        echo '<p><a href="' . esc_url(admin_url('edit.php?post_type=product&page=polski-stock-export')) . '" class="button">' . esc_html__('Back to export', 'polski') . '</a></p>';
        echo '</div>';
        exit;
    }

    /**
     * @return list<string>
     */
    private function getProductCategoryNames(\WC_Product $product): array
    {
        $terms = wp_get_post_terms($product->get_parent_id() ?: $product->get_id(), 'product_cat', ['fields' => 'names']);

        if (! is_array($terms)) {
            return [];
        }

        return array_values(array_map('strval', $terms));
    }

    /**
     * @param list<string> $fields
     * @return list<string>
     */
    private function buildRow(\WC_Product $product, array $fields): array
    {
        $row = [];

        foreach ($fields as $field) {
            $row[] = match ($field) {
                'id' => (string) $product->get_id(),
                'sku' => $product->get_sku(),
                'name' => $product->get_name(),
                'type' => $product->get_type(),
                'stock' => $product->managing_stock() ? (string) $product->get_stock_quantity() : __('Not managed', 'polski'),
                'stock_status' => $product->get_stock_status(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'categories' => implode(' | ', $this->getProductCategoryNames($product)),
                'weight' => $product->get_weight(),
                default => '',
            };
        }

        return $row;
    }

    /**
     * @param list<string> $fields
     * @return list<string>
     */
    private function getFieldLabels(array $fields): array
    {
        $labels = [
            'id' => __('ID', 'polski'),
            'sku' => __('SKU', 'polski'),
            'name' => __('Name', 'polski'),
            'type' => __('Type', 'polski'),
            'stock' => __('Stock', 'polski'),
            'stock_status' => __('Status', 'polski'),
            'regular_price' => __('Regular Price', 'polski'),
            'sale_price' => __('Sale Price', 'polski'),
            'categories' => __('Categories', 'polski'),
            'weight' => __('Weight', 'polski'),
        ];

        return array_map(fn (string $f) => $labels[$f] ?? $f, $fields);
    }
}
