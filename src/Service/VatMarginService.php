<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Procedura marzy (VAT margin scheme) of art. 120 ustawy o VAT.
 *
 * For second-hand goods, works of art, collectors' items and antiques bought in
 * without deductible VAT, the tax base is the dealer's margin rather than the
 * whole price. Two things follow for a shop, and both are on the invoice:
 *
 *  1. The invoice must not show a VAT rate or amount for those lines.
 *  2. It must carry the annotation naming the scheme, art. 106e ust. 3.
 *
 * The scheme is per line, but the annotation is a property of the document, and
 * an invoice cannot sensibly present margin lines and ordinary taxed lines under
 * one VAT summary. Where an order mixes the two this module annotates the
 * invoice and leaves the VAT summary alone rather than suppressing figures that
 * belong to the ordinary lines: a merchant issuing such an order needs to split
 * it, and quietly hiding the VAT would hide that from them.
 *
 * This module changes what an invoice says. It does not compute the margin or
 * decide whether goods qualify, which depends on how they were acquired and is
 * the merchant's call.
 *
 * Optional module, OFF by default.
 */
final class VatMarginService implements HasHooks
{
    public const META_SCHEME = '_polski_vat_margin';

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('vat_margin');
    }

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_filter('polski/invoice/data', [$this, 'annotateInvoice'], 10, 2);
        add_action('woocommerce_single_product_summary', [$this, 'renderProductNotice'], 12);
    }

    /**
     * The three schemes art. 120 distinguishes, with the wording art. 106e ust. 3
     * requires on the invoice.
     *
     * @return array<string, string>
     */
    public static function schemes(): array
    {
        return [
            '' => __('None (ordinary VAT)', 'polski'),
            'used' => __('Second-hand goods', 'polski'),
            'art' => __('Works of art', 'polski'),
            'collectors' => __('Collectors\' items and antiques', 'polski'),
        ];
    }

    /**
     * The statutory annotation for a scheme. Deliberately not translated: it is a
     * phrase Polish law puts on a Polish invoice, not interface text.
     */
    public static function annotationFor(string $scheme): string
    {
        return match ($scheme) {
            'used' => 'procedura marży - towary używane',
            'art' => 'procedura marży - dzieła sztuki',
            'collectors' => 'procedura marży - przedmioty kolekcjonerskie i antyki',
            default => '',
        };
    }

    public function schemeFor(\WC_Product $product): string
    {
        $scheme = (string) $product->get_meta(self::META_SCHEME, true);

        if ($scheme === '' && $product->get_parent_id() > 0) {
            $parent = wc_get_product($product->get_parent_id());
            if ($parent instanceof \WC_Product) {
                $scheme = (string) $parent->get_meta(self::META_SCHEME, true);
            }
        }

        return self::annotationFor($scheme) === '' ? '' : $scheme;
    }

    /**
     * Schemes present on an order, and whether any ordinary line sits alongside.
     *
     * @return array{schemes: list<string>, mixed: bool}
     */
    public function schemesOnOrder(\WC_Order $order): array
    {
        $schemes = [];
        $ordinary = false;

        foreach ($order->get_items() as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();

            if (! $product instanceof \WC_Product) {
                continue;
            }

            $scheme = $this->schemeFor($product);

            if ($scheme === '') {
                $ordinary = true;
                continue;
            }

            if (! in_array($scheme, $schemes, true)) {
                $schemes[] = $scheme;
            }
        }

        return ['schemes' => $schemes, 'mixed' => $schemes !== [] && $ordinary];
    }

    /**
     * @param array<string, mixed>|mixed $data
     * @return array<string, mixed>|mixed
     */
    public function annotateInvoice(mixed $data, \WC_Order $order): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $found = $this->schemesOnOrder($order);

        if ($found['schemes'] === []) {
            return $data;
        }

        $annotations = is_array($data['annotations'] ?? null) ? $data['annotations'] : [];

        foreach ($found['schemes'] as $scheme) {
            $annotations[] = self::annotationFor($scheme);
        }

        if ($found['mixed']) {
            $annotations[] = __('Warning: this order mixes margin-scheme goods with ordinary taxed goods. They cannot share one invoice; issue them separately.', 'polski');
        } else {
            // Every line is under the scheme, so no VAT may be shown.
            $data['vat'] = [];
        }

        $data['annotations'] = array_values(array_unique($annotations));

        return $data;
    }

    public function renderProductNotice(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        if ($this->schemeFor($product) === '') {
            return;
        }

        printf(
            '<p class="polski-vat-margin-notice">%s</p>',
            esc_html__('Sold under the VAT margin scheme. The invoice for this item shows no VAT amount, so the VAT cannot be deducted.', 'polski'),
        );
    }
}
