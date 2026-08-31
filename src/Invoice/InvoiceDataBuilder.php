<?php

declare(strict_types=1);

namespace Polski\Invoice;

use WC_Order;

defined('ABSPATH') || exit;

/**
 * Turns an order into the document an invoice freezes.
 *
 * Everything is resolved once, here: the seller's details as they are today, the
 * buyer's as they were on the order, the lines with their own VAT rates, and a
 * VAT breakdown grouped by rate. Nothing downstream reads the order again.
 */
final class InvoiceDataBuilder
{
    /**
     * @return array{seller: array<string, string>, buyer: array<string, string>, lines: list<array<string, mixed>>, vat: list<array<string, mixed>>, payment: array<string, string>}
     */
    public function build(WC_Order $order): array
    {
        return [
            'seller'  => $this->seller(),
            'buyer'   => $this->buyer($order),
            'lines'   => $this->lines($order),
            'vat'     => $this->vatBreakdown($order),
            'payment' => [
                'method'   => $order->get_payment_method_title(),
                'order'    => $order->get_order_number(),
                'currency' => $order->get_currency(),
            ],
        ];
    }

    /**
     * The shop's own details. Falls back to WooCommerce's store address so an
     * invoice is never issued with an empty seller just because the plugin's own
     * settings were left blank.
     *
     * @return array<string, string>
     */
    private function seller(): array
    {
        $general = get_option('polski_general', []);
        $general = is_array($general) ? $general : [];

        $name = (string) ($general['company_name'] ?? '');
        if ('' === $name) {
            $name = (string) get_option('blogname', '');
        }

        $address = (string) ($general['company_address'] ?? $general['address'] ?? '');
        if ('' === $address) {
            $address = trim(sprintf(
                "%s\n%s %s",
                (string) get_option('woocommerce_store_address', ''),
                (string) get_option('woocommerce_store_postcode', ''),
                (string) get_option('woocommerce_store_city', ''),
            ));
        }

        return [
            'name'    => $name,
            'address' => $address,
            'nip'     => (string) ($general['company_nip'] ?? $general['nip'] ?? ''),
            'email'   => (string) ($general['company_email'] ?? $general['email'] ?? get_option('admin_email', '')),
            'phone'   => (string) ($general['company_phone'] ?? $general['phone'] ?? ''),
            'bank'    => (string) ($general['bank_account'] ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buyer(WC_Order $order): array
    {
        $company = $order->get_billing_company();
        $person  = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());

        // A NIP means the buyer is a business, and the invoice is made out to the
        // company. Both the free NIP module and the B2B fields write it, and the
        // block checkout stores it under its own additional-fields key, so check
        // every place it can legitimately be.
        $nip = (string) ($order->get_meta('_billing_nip', true) ?: '');
        if ('' === $nip) {
            $nip = (string) ($order->get_meta('_wc_billing/polski/nip', true) ?: '');
        }
        if ('' === $nip) {
            $nip = (string) ($order->get_meta('_wc_other/polski/nip', true) ?: '');
        }

        return [
            'name'    => '' !== $company ? $company : $person,
            'person'  => $person,
            'address' => trim(sprintf(
                "%s %s\n%s %s\n%s",
                $order->get_billing_address_1(),
                $order->get_billing_address_2(),
                $order->get_billing_postcode(),
                $order->get_billing_city(),
                $order->get_billing_country(),
            )),
            'nip'   => $nip,
            'email' => $order->get_billing_email(),
        ];
    }

    /**
     * One row per order line, with its own net, VAT rate and gross.
     *
     * The rate is derived from the line's own tax and net rather than read from
     * a tax class, because a single order can carry several rates and the class
     * is not what was actually charged.
     *
     * @return list<array<string, mixed>>
     */
    private function lines(WC_Order $order): array
    {
        $lines = [];

        foreach ($order->get_items() as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $net = (float) $item->get_total();
            $tax = (float) $item->get_total_tax();
            $lines[] = [
                'name'     => $item->get_name(),
                'quantity' => (float) $item->get_quantity(),
                'net'      => $net,
                'tax'      => $tax,
                'gross'    => $net + $tax,
                'rate'     => $this->rate($net, $tax),
            ];
        }

        foreach ($order->get_items('shipping') as $shipping) {
            if (! $shipping instanceof \WC_Order_Item_Shipping) {
                continue;
            }

            $net = (float) $shipping->get_total();
            $tax = (float) $shipping->get_total_tax();

            if (0.0 === $net && 0.0 === $tax) {
                continue;
            }

            $lines[] = [
                'name'     => $shipping->get_name(),
                'quantity' => 1.0,
                'net'      => $net,
                'tax'      => $tax,
                'gross'    => $net + $tax,
                'rate'     => $this->rate($net, $tax),
            ];
        }

        foreach ($order->get_items('fee') as $fee) {
            if (! $fee instanceof \WC_Order_Item_Fee) {
                continue;
            }

            $net = (float) $fee->get_total();
            $tax = (float) $fee->get_total_tax();

            $lines[] = [
                'name'     => $fee->get_name(),
                'quantity' => 1.0,
                'net'      => $net,
                'tax'      => $tax,
                'gross'    => $net + $tax,
                'rate'     => $this->rate($net, $tax),
            ];
        }

        return $lines;
    }

    /**
     * Net and VAT summed per rate, which is the part an accountant reads first.
     *
     * @return list<array<string, mixed>>
     */
    private function vatBreakdown(WC_Order $order): array
    {
        $byRate = [];

        foreach ($this->lines($order) as $line) {
            $rate = (string) $line['rate'];

            if (! isset($byRate[$rate])) {
                $byRate[$rate] = ['rate' => $line['rate'], 'net' => 0.0, 'tax' => 0.0, 'gross' => 0.0];
            }

            $byRate[$rate]['net']   += (float) $line['net'];
            $byRate[$rate]['tax']   += (float) $line['tax'];
            $byRate[$rate]['gross'] += (float) $line['gross'];
        }

        krsort($byRate, SORT_NUMERIC);

        return array_values($byRate);
    }

    /**
     * The effective VAT rate for a line, as a whole-number percentage.
     *
     * A zero net with any tax cannot yield a meaningful rate, so it reports 0
     * rather than dividing by zero and producing INF on the document.
     */
    private function rate(float $net, float $tax): float
    {
        if (0.0 === round($net, 6)) {
            return 0.0;
        }

        return round(($tax / $net) * 100, 0);
    }
}
