<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Custom checkout fields manager.
 *
 * Add, modify, reorder, and conditionally display checkout fields.
 * Supports field types: text, textarea, select, checkbox, number, email, date.
 * Fields are saved as order meta and displayed in admin, emails, and My Account.
 *
 * Inspired by Flexible Checkout Fields but with a simpler, WordPress-native approach.
 *
 * @phpstan-type CheckoutField array{
 *     name: string,
 *     label: string,
 *     type: string,
 *     section: string,
 *     required: bool,
 *     priority: int,
 *     placeholder: string,
 *     options: string,
 *     css_class: string,
 *     show_in_email: bool,
 *     show_in_admin: bool,
 *     show_in_account: bool,
 *     enabled: bool,
 *     conditional_shipping: string,
 *     conditional_payment: string,
 *     conditional_field: string,
 *     conditional_value: string,
 *     conditional_category: int,
 *     conditional_cart_min: float
 * }
 */
final class CustomCheckoutFieldsService implements HasHooks
{
    private const OPTION = 'polski_custom_checkout_fields';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('custom_checkout_fields')) {
            return;
        }

        // Modify checkout fields.
        add_filter('woocommerce_checkout_fields', [$this, 'modifyCheckoutFields'], 20);

        // Validate custom fields.
        add_action('woocommerce_checkout_process', [$this, 'validateFields']);

        // Save custom fields to order meta.
        add_action('woocommerce_checkout_update_order_meta', [$this, 'saveFieldsToOrder']);

        // Display in admin order.
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'displayInAdmin']);
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'displayInAdminShipping']);

        // Display in order emails.
        add_action('woocommerce_email_after_order_table', [$this, 'displayInEmail'], 15, 3);

        // Display in My Account order detail.
        add_action('woocommerce_order_details_after_order_table', [$this, 'displayInAccountOrder']);

        // Admin settings page.
        add_action('admin_menu', [$this, 'addAdminPage']);
        add_action('admin_post_polski_save_checkout_fields', [$this, 'handleSave']);
    }

    /**
     * Get configured custom fields (normalized for consistent keys and types).
     *
     * @return list<CheckoutField>
     */
    public function getFields(): array
    {
        $raw = get_option(self::OPTION, []);

        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $out[] = $this->normalizeCheckoutField($row);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return CheckoutField
     */
    private function normalizeCheckoutField(array $row): array
    {
        return [
            'name' => sanitize_key($row['name'] ?? ''),
            'label' => sanitize_text_field($row['label'] ?? ''),
            'type' => sanitize_text_field($row['type'] ?? 'text'),
            'section' => sanitize_text_field($row['section'] ?? 'billing'),
            'required' => ! empty($row['required']),
            'priority' => max(1, min(999, (int) ($row['priority'] ?? 100))),
            'placeholder' => sanitize_text_field($row['placeholder'] ?? ''),
            'options' => sanitize_textarea_field($row['options'] ?? ''),
            'css_class' => sanitize_text_field($row['css_class'] ?? 'form-row-wide'),
            'show_in_email' => ! empty($row['show_in_email']),
            'show_in_admin' => ! empty($row['show_in_admin']),
            'show_in_account' => ! empty($row['show_in_account']),
            'enabled' => ! empty($row['enabled']),
            'conditional_shipping' => sanitize_text_field($row['conditional_shipping'] ?? ''),
            'conditional_payment' => sanitize_text_field($row['conditional_payment'] ?? ''),
            'conditional_field' => sanitize_key($row['conditional_field'] ?? ''),
            'conditional_value' => sanitize_text_field($row['conditional_value'] ?? ''),
            'conditional_category' => absint($row['conditional_category'] ?? 0),
            'conditional_cart_min' => (float) ($row['conditional_cart_min'] ?? 0),
        ];
    }

    /**
     * Inject custom fields into the checkout form.
     *
     * @param array<string, array<string, array<string, mixed>>> $fields
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function modifyCheckoutFields(array $fields): array
    {
        foreach ($this->getFields() as $field) {
            if (empty($field['enabled'])) {
                continue;
            }

            $section = $field['section'];
            $key = $field['name'];

            if ($key === '') {
                continue;
            }

            $fieldConfig = [
                'type' => $field['type'],
                'label' => $field['label'],
                'required' => $field['required'],
                'priority' => $field['priority'],
                'placeholder' => $field['placeholder'],
                'class' => array_filter(explode(' ', $field['css_class'])),
            ];

            // Select/radio options.
            if (in_array($fieldConfig['type'], ['select', 'radio'], true) && ! empty($field['options'])) {
                $opts = [];

                foreach (explode("\n", $field['options']) as $line) {
                    $line = trim($line);

                    if (str_contains($line, '|')) {
                        [$val, $label] = explode('|', $line, 2);
                        $opts[trim($val)] = trim($label);
                    } else {
                        $opts[$line] = $line;
                    }
                }

                $fieldConfig['options'] = $opts;
            }

            // Conditional visibility logic.
            if (! $this->evaluateConditions($field)) {
                continue;
            }

            if (! isset($fields[$section])) {
                $fields[$section] = [];
            }

            $fields[$section][$key] = $fieldConfig;
        }

        return $fields;
    }

    /**
     * Validate required custom fields.
     */
    public function validateFields(): void
    {
        if (! $this->isVerifiedCheckoutPost()) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce checkout nonce verified above.
        foreach ($this->getFields() as $field) {
            if (empty($field['enabled']) || empty($field['required'])) {
                continue;
            }

            $name = $field['name'];
            $value = $_POST[$name] ?? '';

            if (empty($value) && $value !== '0') {
                $label = $field['label'];
                wc_add_notice(
                    sprintf(
                        /* translators: %s: field label */
                        __('%s is a required field.', 'polski'),
                        '<strong>' . esc_html($label) . '</strong>',
                    ),
                    'error',
                );
            }

            // Email validation.
            if ($field['type'] === 'email' && ! empty($value) && ! is_email($value)) {
                wc_add_notice(
                    sprintf(
                        /* translators: %s: field label */
                        __('%s is not a valid email address.', 'polski'),
                        '<strong>' . esc_html($field['label']) . '</strong>',
                    ),
                    'error',
                );
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    /**
     * Save custom field values as order meta.
     */
    public function saveFieldsToOrder(int $orderId): void
    {
        if (! $this->isVerifiedCheckoutPost()) {
            return;
        }

        $order = wc_get_order($orderId);

        if (! $order) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce checkout nonce verified above.
        foreach ($this->getFields() as $field) {
            if (empty($field['enabled'])) {
                continue;
            }

            $name = $field['name'];

            if ($name === '' || ! isset($_POST[$name])) {
                continue;
            }

            $value = $field['type'] === 'textarea'
                ? sanitize_textarea_field($_POST[$name])
                : sanitize_text_field($_POST[$name]);

            $order->update_meta_data('_' . $name, $value);
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $order->save();
    }

    /**
     * Display custom billing fields in admin order detail.
     *
     * @param \WC_Order $order
     */
    public function displayInAdmin($order): void
    {
        $this->renderAdminFields($order, 'billing');
    }

    /**
     * @param \WC_Order $order
     */
    public function displayInAdminShipping($order): void
    {
        $this->renderAdminFields($order, 'shipping');
    }

    private function renderAdminFields(\WC_Order $order, string $section): void
    {
        foreach ($this->getFields() as $field) {
            if (empty($field['enabled']) || empty($field['show_in_admin'])) {
                continue;
            }

            if ($field['section'] !== $section) {
                continue;
            }

            $name = $field['name'];
            $value = $order->get_meta('_' . $name);

            if (empty($value) && $value !== '0') {
                continue;
            }

            printf(
                '<p><strong>%s:</strong> %s</p>',
                esc_html($field['label']),
                esc_html($value),
            );
        }
    }

    /**
     * Display custom fields in order emails.
     *
     * @param \WC_Order $order
     * @param bool      $sentToAdmin
     * @param bool      $plainText
     */
    public function displayInEmail($order, $sentToAdmin, $plainText): void
    {
        $output = '';

        foreach ($this->getFields() as $field) {
            if (empty($field['enabled']) || empty($field['show_in_email'])) {
                continue;
            }

            $name = $field['name'];
            $value = $order->get_meta('_' . $name);

            if (empty($value) && $value !== '0') {
                continue;
            }

            if ($plainText) {
                $output .= esc_html($field['label']) . ': ' . esc_html($value) . "\n";
            } else {
                $output .= sprintf(
                    '<p><strong>%s:</strong> %s</p>',
                    esc_html($field['label']),
                    esc_html($value),
                );
            }
        }

        if ($output) {
            echo wp_kses_post($output);
        }
    }

    /**
     * Display in My Account order detail.
     *
     * @param \WC_Order $order
     */
    public function displayInAccountOrder($order): void
    {
        $hasOutput = false;

        foreach ($this->getFields() as $field) {
            if (empty($field['enabled']) || empty($field['show_in_account'])) {
                continue;
            }

            $name = $field['name'];
            $value = $order->get_meta('_' . $name);

            if (empty($value) && $value !== '0') {
                continue;
            }

            if (! $hasOutput) {
                echo '<h2>' . esc_html__('Additional information', 'polski') . '</h2>';
                $hasOutput = true;
            }

            printf(
                '<p><strong>%s:</strong> %s</p>',
                esc_html($field['label']),
                esc_html($value),
            );
        }
    }

    // ── Conditional Logic ────────────────────────────────

    /**
     * Evaluate all conditions for a field.
     *
     * Conditions supported:
     * - conditional_shipping: show only for specific shipping method
     * - conditional_payment: show only for specific payment method
     * - conditional_field: show only when another field has a specific value
     * - conditional_category: show only when a product from category is in cart
     * - conditional_cart_min: show only when cart total >= value
     *
     * @param CheckoutField $field
     */
    private function evaluateConditions(array $field): bool
    {
        // Shipping method condition.
        if (! empty($field['conditional_shipping'])) {
            $chosen = WC()->session->get('chosen_shipping_methods', []);
            $chosenMethod = $chosen[0] ?? '';

            if (! empty($chosenMethod) && ! str_starts_with($chosenMethod, $field['conditional_shipping'])) {
                return false;
            }
        }

        // Payment method condition.
        if (! empty($field['conditional_payment'])) {
            $chosenPayment = WC()->session->get('chosen_payment_method', '');

            if (! empty($chosenPayment) && $chosenPayment !== $field['conditional_payment']) {
                return false;
            }
        }

        // Another field value condition (e.g., show NIP field only if "needs_invoice" is checked).
        if (! empty($field['conditional_field']) && ! empty($field['conditional_value'])) {
            $otherFieldName = $field['conditional_field'];
            $expectedValue = $field['conditional_value'];

            $actualValue = $this->readTrustedCheckoutPostString($otherFieldName);

            if ((string) $actualValue !== (string) $expectedValue) {
                return false;
            }
        }

        // Category condition: show only when cart contains product from category.
        if (! empty($field['conditional_category'])) {
            $categoryId = (int) $field['conditional_category'];
            $found = false;

            foreach (WC()->cart->get_cart() as $item) {
                $product = $item['data'] ?? null;

                if ($product instanceof \WC_Product && in_array($categoryId, $product->get_category_ids(), true)) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return false;
            }
        }

        // Minimum cart total condition.
        if (! empty($field['conditional_cart_min'])) {
            $minTotal = (float) $field['conditional_cart_min'];

            if ((float) WC()->cart->get_subtotal() < $minTotal) {
                return false;
            }
        }

        return true;
    }

    // ── Admin UI ────────────────────────────────────────

    public function addAdminPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Checkout Fields', 'polski'),
            __('Checkout Fields', 'polski'),
            'manage_woocommerce',
            'polski-checkout-fields',
            [$this, 'renderAdminPage'],
        );
    }

    public function renderAdminPage(): void
    {
        $fields = $this->getFields();
        $fieldTypes = [
            'text' => __('Text', 'polski'),
            'textarea' => __('Textarea', 'polski'),
            'select' => __('Select', 'polski'),
            'checkbox' => __('Checkbox', 'polski'),
            'radio' => __('Radio', 'polski'),
            'number' => __('Number', 'polski'),
            'email' => __('Email', 'polski'),
            'date' => __('Date', 'polski'),
            'tel' => __('Phone', 'polski'),
        ];

        $sections = [
            'billing' => __('Billing', 'polski'),
            'shipping' => __('Shipping', 'polski'),
            'order' => __('Order notes', 'polski'),
        ];

        echo '<div class="wrap"><h1>' . esc_html__('Custom Checkout Fields', 'polski') . '</h1>';

        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Fields saved.', 'polski') . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('polski_checkout_fields', '_polski_cf_nonce');
        echo '<input type="hidden" name="action" value="polski_save_checkout_fields">';

        echo '<table class="widefat fixed striped" id="polski-checkout-fields">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Enabled', 'polski') . '</th>';
        echo '<th>' . esc_html__('Name (meta key)', 'polski') . '</th>';
        echo '<th>' . esc_html__('Label', 'polski') . '</th>';
        echo '<th>' . esc_html__('Type', 'polski') . '</th>';
        echo '<th>' . esc_html__('Section', 'polski') . '</th>';
        echo '<th>' . esc_html__('Required', 'polski') . '</th>';
        echo '<th>' . esc_html__('Priority', 'polski') . '</th>';
        echo '<th>' . esc_html__('Actions', 'polski') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($fields as $i => $field) {
            $this->renderFieldRow($i, $field, $fieldTypes, $sections);
        }

        // Empty row for adding new field.
        $this->renderFieldRow(count($fields), [], $fieldTypes, $sections);

        echo '</tbody></table>';

        echo '<p><button type="button" class="button" onclick="addFieldRow()">' . esc_html__('Add field', 'polski') . '</button></p>';

        submit_button(__('Save fields', 'polski'));

        echo '</form>';

        $this->renderAdminScript();

        echo '</div>';
    }

    /**
     * @param array<string, string> $fieldTypes
     * @param array<string, string> $sections
     * @param array<string, mixed>  $field
     */
    private function renderFieldRow(int $index, array $field, array $fieldTypes, array $sections): void
    {
        $prefix = "fields[{$index}]";

        echo '<tr>';

        // Enabled.
        printf(
            '<td><input type="checkbox" name="%s[enabled]" value="1" %s></td>',
            esc_attr($prefix),
            checked(! empty($field['enabled']), true, false),
        );

        // Name.
        printf(
            '<td><input type="text" name="%s[name]" value="%s" class="regular-text" placeholder="e.g. billing_vat_number"></td>',
            esc_attr($prefix),
            esc_attr($field['name'] ?? ''),
        );

        // Label.
        printf(
            '<td><input type="text" name="%s[label]" value="%s" class="regular-text"></td>',
            esc_attr($prefix),
            esc_attr($field['label'] ?? ''),
        );

        // Type.
        echo '<td><select name="' . esc_attr($prefix) . '[type]">';

        foreach ($fieldTypes as $typeVal => $typeLabel) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($typeVal),
                selected($field['type'] ?? 'text', $typeVal, false),
                esc_html($typeLabel),
            );
        }

        echo '</select></td>';

        // Section.
        echo '<td><select name="' . esc_attr($prefix) . '[section]">';

        foreach ($sections as $secVal => $secLabel) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($secVal),
                selected($field['section'] ?? 'billing', $secVal, false),
                esc_html($secLabel),
            );
        }

        echo '</select></td>';

        // Required.
        printf(
            '<td><input type="checkbox" name="%s[required]" value="1" %s></td>',
            esc_attr($prefix),
            checked(! empty($field['required']), true, false),
        );

        // Priority.
        printf(
            '<td><input type="number" name="%s[priority]" value="%d" min="1" max="999" style="width:60px"></td>',
            esc_attr($prefix),
            (int) ($field['priority'] ?? 100),
        );

        // Actions (hidden fields for advanced settings).
        echo '<td>';

        // Placeholder.
        printf(
            '<input type="hidden" name="%s[placeholder]" value="%s">',
            esc_attr($prefix),
            esc_attr($field['placeholder'] ?? ''),
        );

        // Options for select/radio.
        printf(
            '<input type="hidden" name="%s[options]" value="%s">',
            esc_attr($prefix),
            esc_attr($field['options'] ?? ''),
        );

        // CSS class.
        printf(
            '<input type="hidden" name="%s[css_class]" value="%s">',
            esc_attr($prefix),
            esc_attr($field['css_class'] ?? 'form-row-wide'),
        );

        // Show in email/admin/account.
        printf(
            '<input type="hidden" name="%s[show_in_email]" value="%s">',
            esc_attr($prefix),
            esc_attr(! empty($field['show_in_email']) ? '1' : '0'),
        );
        printf(
            '<input type="hidden" name="%s[show_in_admin]" value="%s">',
            esc_attr($prefix),
            esc_attr(! empty($field['show_in_admin']) ? '1' : '0'),
        );
        printf(
            '<input type="hidden" name="%s[show_in_account]" value="%s">',
            esc_attr($prefix),
            esc_attr(! empty($field['show_in_account']) ? '1' : '0'),
        );

        // Conditional shipping.
        printf(
            '<input type="hidden" name="%s[conditional_shipping]" value="%s">',
            esc_attr($prefix),
            esc_attr($field['conditional_shipping'] ?? ''),
        );

        echo '</td></tr>';
    }

    private function renderAdminScript(): void
    {
        echo '<script>
        var fieldIndex = document.querySelectorAll("#polski-checkout-fields tbody tr").length;
        function addFieldRow() {
            var tbody = document.querySelector("#polski-checkout-fields tbody");
            var firstRow = tbody.querySelector("tr");
            var newRow = firstRow.cloneNode(true);
            newRow.querySelectorAll("input, select").forEach(function(el) {
                el.name = el.name.replace(/fields\[\d+\]/, "fields[" + fieldIndex + "]");
                if (el.type === "checkbox") el.checked = false;
                else if (el.type === "text" || el.type === "number") el.value = el.type === "number" ? "100" : "";
            });
            tbody.appendChild(newRow);
            fieldIndex++;
        }
        </script>';
    }

    /**
     * Handle admin form save.
     */
    public function handleSave(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Unauthorized', 'polski'));
        }

        check_admin_referer('polski_checkout_fields', '_polski_cf_nonce');

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Admin referer verified above.
        $rawFields = $_POST['fields'] ?? [];

        if (! is_array($rawFields)) {
            $rawFields = [];
        }

        $fields = [];

        foreach ($rawFields as $field) {
            $name = sanitize_key($field['name'] ?? '');

            if (empty($name)) {
                continue;
            }

            $fields[] = [
                'name' => $name,
                'label' => sanitize_text_field($field['label'] ?? ''),
                'type' => sanitize_text_field($field['type'] ?? 'text'),
                'section' => sanitize_text_field($field['section'] ?? 'billing'),
                'required' => ! empty($field['required']),
                'priority' => max(1, min(999, (int) ($field['priority'] ?? 100))),
                'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                'options' => sanitize_textarea_field($field['options'] ?? ''),
                'css_class' => sanitize_text_field($field['css_class'] ?? 'form-row-wide'),
                'show_in_email' => ! empty($field['show_in_email']),
                'show_in_admin' => ! empty($field['show_in_admin']),
                'show_in_account' => ! empty($field['show_in_account']),
                'enabled' => ! empty($field['enabled']),
                'conditional_shipping' => sanitize_text_field($field['conditional_shipping'] ?? ''),
                'conditional_payment' => sanitize_text_field($field['conditional_payment'] ?? ''),
                'conditional_field' => sanitize_key($field['conditional_field'] ?? ''),
                'conditional_value' => sanitize_text_field($field['conditional_value'] ?? ''),
                'conditional_category' => absint($field['conditional_category'] ?? 0),
                'conditional_cart_min' => (float) ($field['conditional_cart_min'] ?? 0),
            ];
        }

        update_option(self::OPTION, $fields);
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        wp_safe_redirect(admin_url('admin.php?page=polski-checkout-fields&saved=1'));
        exit;
    }

    private function isVerifiedCheckoutPost(): bool
    {
        $nonce = isset($_POST['woocommerce-process-checkout-nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['woocommerce-process-checkout-nonce']))
            : '';

        return $nonce !== '' && wp_verify_nonce($nonce, 'woocommerce-process_checkout');
    }

    private function canTrustCheckoutPostData(): bool
    {
        if ($this->isVerifiedCheckoutPost()) {
            return true;
        }

        if (! wp_doing_ajax()) {
            return false;
        }

        $security = isset($_POST['security'])
            ? sanitize_text_field(wp_unslash((string) $_POST['security']))
            : '';

        return $security !== '' && wp_verify_nonce($security, 'woocommerce-update-order-review');
    }

    private function readTrustedCheckoutPostString(string $fieldName): string
    {
        if (! $this->canTrustCheckoutPostData()) {
            return '';
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in canTrustCheckoutPostData().
        $value = isset($_POST[$fieldName])
            ? sanitize_text_field(wp_unslash($_POST[$fieldName]))
            : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        return $value;
    }
}
