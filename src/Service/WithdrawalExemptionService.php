<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Enum\WithdrawalExemptionReason;

/**
 * Centralised exemption checks for the withdrawal flow.
 *
 * A product can be excluded from the right of withdrawal in two ways:
 *   1. Directly on the product (existing `_polski_withdrawal_exempt` meta).
 *   2. Through its product category (`polski_withdrawal_exempt` term meta).
 *
 * Both can carry an optional reason from {@see WithdrawalExemptionReason} so the
 * storefront can explain to the consumer why a particular product is excluded.
 *
 * Admin UI: category edit screen gets a "Exclude from withdrawal" toggle + reason
 * dropdown. The product-level fields are rendered by {@see \Polski\Admin\ProductMetaBox}.
 */
final class WithdrawalExemptionService implements HasHooks
{
    public const PRODUCT_META = '_polski_withdrawal_exempt';
    public const PRODUCT_REASON_META = '_polski_withdrawal_exempt_reason';
    public const PRODUCT_REASON_CUSTOM_META = '_polski_withdrawal_exempt_reason_custom';
    public const TERM_META = 'polski_withdrawal_exempt';
    public const TERM_REASON_META = 'polski_withdrawal_exempt_reason';
    public const TERM_REASON_CUSTOM_META = 'polski_withdrawal_exempt_reason_custom';

    public function registerHooks(): void
    {
        // Category screens.
        add_action('product_cat_add_form_fields', [$this, 'renderAddTermFields']);
        add_action('product_cat_edit_form_fields', [$this, 'renderEditTermFields']);
        add_action('created_product_cat', [$this, 'saveTermFields']);
        add_action('edited_product_cat', [$this, 'saveTermFields']);

        // Plug into the central eligibility filter so this service is the single source of truth.
        add_filter('polski/withdrawal/eligible', [$this, 'filterOrderEligibility'], 10, 2);

        // Decorate per-item rows with is_exempt + exempt_reason so the form
        // can render exempt items as info-only and the server-side selection
        // parser can drop them in a mixed cart.
        add_filter('polski/withdrawal/items', [$this, 'decorateRowsWithExemption'], 10, 2);
    }

    /**
     * Mark each item row with `is_exempt` + `exempt_reason` when the product
     * (or one of its categories) is excluded from the right of withdrawal.
     *
     * Subscribers further down the chain receive the decorated row and can
     * skip exempt items without re-running the lookup themselves.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function decorateRowsWithExemption(array $rows, \WC_Order $order): array
    {
        unset($order);

        foreach ($rows as &$row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $product = wc_get_product($productId);
            if (! $product instanceof \WC_Product) {
                continue;
            }

            if ($this->isProductExempt($product)) {
                $row['is_exempt'] = true;
                $row['exempt_reason'] = $this->getProductExemptionReason($product);
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Returns true if the product itself, or any of its categories, is marked
     * as excluded from the right of withdrawal.
     */
    public function isProductExempt(\WC_Product $product): bool
    {
        if ($product->get_meta(self::PRODUCT_META, true) === 'yes') {
            return true;
        }

        $parentId = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
        $terms = wp_get_post_terms($parentId, 'product_cat', ['fields' => 'ids']);

        if (! is_array($terms) || $terms === []) {
            return false;
        }

        foreach ($terms as $termId) {
            if (get_term_meta((int) $termId, self::TERM_META, true) === 'yes') {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the human-readable reason for the exemption, suitable for display
     * on the storefront. Falls back to a custom free-text value if the merchant
     * picked {@see WithdrawalExemptionReason::Custom}.
     */
    public function getProductExemptionReason(\WC_Product $product): string
    {
        // Product-level overrides category-level.
        $productReason = (string) $product->get_meta(self::PRODUCT_REASON_META, true);
        if ($productReason !== '') {
            return $this->resolveReason($productReason, (string) $product->get_meta(self::PRODUCT_REASON_CUSTOM_META, true));
        }

        $parentId = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
        $terms = wp_get_post_terms($parentId, 'product_cat', ['fields' => 'ids']);

        if (! is_array($terms)) {
            return '';
        }

        foreach ($terms as $termId) {
            if (get_term_meta((int) $termId, self::TERM_META, true) !== 'yes') {
                continue;
            }

            $reason = (string) get_term_meta((int) $termId, self::TERM_REASON_META, true);
            if ($reason !== '') {
                return $this->resolveReason($reason, (string) get_term_meta((int) $termId, self::TERM_REASON_CUSTOM_META, true));
            }
        }

        return '';
    }

    /**
     * Eligibility filter: if every line item in the order is exempt, the order is
     * not eligible. (When item-level partial withdrawals land, this will be relaxed
     * to "if at least one item is not exempt".)
     */
    public function filterOrderEligibility(bool $eligible, \WC_Order $order): bool
    {
        if (! $eligible) {
            return false;
        }

        $hasNonExempt = false;

        foreach ($order->get_items() as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $product = $item->get_product();
            if (! $product instanceof \WC_Product) {
                continue;
            }
            if (! $this->isProductExempt($product)) {
                $hasNonExempt = true;
                break;
            }
        }

        return $hasNonExempt;
    }

    /**
     * Render Add form fields for product_cat. The "Add" screen does not pre-fill from
     * existing term meta - just emits the controls.
     */
    public function renderAddTermFields(): void
    {
        ?>
        <div class="form-field">
            <label for="polski_withdrawal_exempt">
                <input type="checkbox" id="polski_withdrawal_exempt" name="polski_withdrawal_exempt" value="yes">
                <?php esc_html_e('Wyklucz produkty z tej kategorii z prawa odstąpienia', 'polski'); ?>
            </label>
            <p class="description"><?php esc_html_e('Zastosuj, gdy wszystkie produkty w kategorii spełniają wyjątek z Art. 38 Ustawy o prawach konsumenta.', 'polski'); ?></p>
        </div>
        <div class="form-field">
            <label for="polski_withdrawal_exempt_reason"><?php esc_html_e('Podstawa wyłączenia', 'polski'); ?></label>
            <select id="polski_withdrawal_exempt_reason" name="polski_withdrawal_exempt_reason">
                <option value=""><?php esc_html_e('- wybierz -', 'polski'); ?></option>
                <?php foreach (WithdrawalExemptionReason::choices() as $choice) : ?>
                    <option value="<?php echo esc_attr($choice['value']); ?>"><?php echo esc_html($choice['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label for="polski_withdrawal_exempt_reason_custom"><?php esc_html_e('Własne uzasadnienie (jeśli wybrano „Inne")', 'polski'); ?></label>
            <input type="text" id="polski_withdrawal_exempt_reason_custom" name="polski_withdrawal_exempt_reason_custom" value="">
        </div>
        <?php
    }

    /**
     * Render Edit form fields for product_cat (table layout).
     */
    public function renderEditTermFields(\WP_Term $term): void
    {
        $exempt = get_term_meta($term->term_id, self::TERM_META, true) === 'yes';
        $reason = (string) get_term_meta($term->term_id, self::TERM_REASON_META, true);
        $custom = (string) get_term_meta($term->term_id, self::TERM_REASON_CUSTOM_META, true);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="polski_withdrawal_exempt"><?php esc_html_e('Prawo odstąpienia', 'polski'); ?></label></th>
            <td>
                <label>
                    <input type="checkbox" id="polski_withdrawal_exempt" name="polski_withdrawal_exempt" value="yes" <?php checked($exempt); ?>>
                    <?php esc_html_e('Wyklucz produkty z tej kategorii z prawa odstąpienia', 'polski'); ?>
                </label>
                <p class="description"><?php esc_html_e('Zastosuj, gdy wszystkie produkty w kategorii spełniają wyjątek z Art. 38 Ustawy o prawach konsumenta.', 'polski'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="polski_withdrawal_exempt_reason"><?php esc_html_e('Podstawa wyłączenia', 'polski'); ?></label></th>
            <td>
                <select id="polski_withdrawal_exempt_reason" name="polski_withdrawal_exempt_reason">
                    <option value=""><?php esc_html_e('- wybierz -', 'polski'); ?></option>
                    <?php foreach (WithdrawalExemptionReason::choices() as $choice) : ?>
                        <option value="<?php echo esc_attr($choice['value']); ?>" <?php selected($reason, $choice['value']); ?>>
                            <?php echo esc_html($choice['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="polski_withdrawal_exempt_reason_custom"><?php esc_html_e('Własne uzasadnienie', 'polski'); ?></label></th>
            <td>
                <input type="text" id="polski_withdrawal_exempt_reason_custom" name="polski_withdrawal_exempt_reason_custom" value="<?php echo esc_attr($custom); ?>" class="regular-text">
                <p class="description"><?php esc_html_e('Używane tylko, jeśli wybrano „Inne".', 'polski'); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save term meta when the category is created or updated. Capability checks
     * are handled by `manage_categories`, which WordPress enforces on these hooks.
     */
    public function saveTermFields(int $termId): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- term save hook fires from core's own nonce-protected form.
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $exempt = isset($_POST['polski_withdrawal_exempt']) ? 'yes' : '';
        update_term_meta($termId, self::TERM_META, $exempt);

        $reason = isset($_POST['polski_withdrawal_exempt_reason'])
            ? sanitize_key((string) wp_unslash($_POST['polski_withdrawal_exempt_reason']))
            : '';
        update_term_meta($termId, self::TERM_REASON_META, $reason);

        $custom = isset($_POST['polski_withdrawal_exempt_reason_custom'])
            ? sanitize_text_field((string) wp_unslash($_POST['polski_withdrawal_exempt_reason_custom']))
            : '';
        update_term_meta($termId, self::TERM_REASON_CUSTOM_META, $custom);
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    private function resolveReason(string $value, string $custom): string
    {
        $case = WithdrawalExemptionReason::tryFrom($value);

        if ($case === null) {
            return $custom !== '' ? $custom : $value;
        }

        if ($case === WithdrawalExemptionReason::Custom) {
            return $custom !== '' ? $custom : $case->label();
        }

        return $case->label();
    }
}
