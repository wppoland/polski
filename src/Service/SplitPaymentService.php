<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Split payment (mechanizm podzielonej płatności, MPP) of art. 108a ust. 1a
 * ustawy o VAT. A B2B invoice over 15 000 zł gross that includes goods or
 * services from annex 15 must carry the words "mechanizm podzielonej
 * płatności". The shop marks annex 15 products; this service decides per order
 * and adds the annotation to the invoice, records it on the order and shows it
 * on the order screen, so Polski PRO can file it to KSeF (P_18A).
 *
 * Only PLN orders are judged: a foreign-currency invoice is compared after
 * conversion at the NBP rate, which the invoice module does when it issues.
 *
 * existing-checked: Service/VatMarginService.php - it owns the margin scheme annotation for one module; split payment applies to any shop, so it is its own always-on service using the same polski/invoice/data filter.
 */
final class SplitPaymentService implements HasHooks
{
    public const META = '_polski_mpp';

    public const ORDER_META = '_polski_mpp_required';

    public const THRESHOLD = 15000.0;

    /** The wording art. 106e ust. 1 pkt 18a requires. Not translated: it belongs on a Polish invoice. */
    public const ANNOTATION = 'mechanizm podzielonej płatności';

    public function registerHooks(): void
    {
        add_filter('polski/invoice/data', [$this, 'annotateInvoice'], 10, 2);
        add_action('woocommerce_checkout_order_processed', [$this, 'markOrder'], 20, 1);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'markOrder'], 30, 1);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'renderAdmin'], 25);
        add_action('polski/order/tax_id_changed', [$this, 'markOrder']);
    }

    public static function productFlagged(\WC_Product $product): bool
    {
        if ($product->get_meta(self::META, true) === 'yes') {
            return true;
        }
        $parent = $product->get_parent_id() > 0 ? wc_get_product($product->get_parent_id()) : null;

        return $parent instanceof \WC_Product && $parent->get_meta(self::META, true) === 'yes';
    }

    /** B2B (buyer NIP), PLN, over 15 000 zł gross, with an annex 15 product. */
    public static function applies(\WC_Order $order): bool
    {
        $applies = strtoupper($order->get_currency()) === 'PLN'
            && (float) $order->get_total() > self::THRESHOLD
            && trim((string) ($order->get_meta('_billing_nip', true) ?: $order->get_meta('_polski_billing_nip', true))) !== ''
            && self::hasFlaggedProduct($order);

        /**
         * Filters whether an order falls under split payment.
         *
         * @param bool      $applies
         * @param \WC_Order $order
         */
        return (bool) apply_filters('polski/mpp/applies', $applies, $order);
    }

    private static function hasFlaggedProduct(\WC_Order $order): bool
    {
        foreach ($order->get_items() as $item) {
            $product = $item instanceof \WC_Order_Item_Product ? $item->get_product() : null;
            if ($product instanceof \WC_Product && self::productFlagged($product)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed>|mixed $data
     * @return array<string, mixed>|mixed
     */
    public function annotateInvoice(mixed $data, \WC_Order $order): mixed
    {
        if (is_array($data) && self::applies($order)) {
            $data['annotations'] = array_merge((array) ($data['annotations'] ?? []), [self::ANNOTATION]);
        }

        return $data;
    }

    public function markOrder(int|\WC_Order $order): void
    {
        $order = $order instanceof \WC_Order ? $order : wc_get_order($order);
        if ($order instanceof \WC_Order) {
            $order->update_meta_data(self::ORDER_META, self::applies($order) ? 'yes' : 'no');
            $order->save();
        }
    }

    public function renderAdmin(\WC_Order $order): void
    {
        // The flag stored at checkout (or after a NIP change); orders from before it are worked out now.
        $stored = (string) $order->get_meta(self::ORDER_META, true);
        if ($stored === 'yes' || ($stored === '' && self::applies($order))) {
            echo '<p><strong>' . esc_html__('Split payment (MPP):', 'polski') . '</strong> '
                . esc_html__('the invoice carries "mechanizm podzielonej płatności" (annex 15 goods, over 15 000 zł, business buyer).', 'polski') . '</p>';
        }
    }
}
