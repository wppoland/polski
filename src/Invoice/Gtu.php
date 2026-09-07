<?php

declare(strict_types=1);

namespace Polski\Invoice;

defined('ABSPATH') || exit;

/**
 * GTU markings (grupy towarowo-uslugowe) for a product.
 *
 * Thirteen codes defined by par. 10 ust. 3 of the rozporzadzenie on the data
 * carried by JPK_V7. A shop marks the goods and services that fall into one of
 * them, and the marking has to travel with the line it belongs to: onto the
 * invoice, and into the FA(2) document when one is sent to KSeF.
 *
 * The code is stored on the product under a single meta key, which is the same
 * key the PRO tax-rules engine writes when it assigns one automatically. Typing
 * it by hand and having a rule set it are two ways to fill the same field, so
 * they must not become two fields.
 */
final class Gtu
{
    public const META = '_polski_gtu_code';

    /**
     * The thirteen codes, each with what it covers.
     *
     * @return array<string, string>
     */
    public static function codes(): array
    {
        return [
            'GTU_01' => __('GTU_01: alcoholic beverages', 'polski'),
            'GTU_02' => __('GTU_02: fuels covered by art. 103 ust. 5aa', 'polski'),
            'GTU_03' => __('GTU_03: heating and lubricating oils', 'polski'),
            'GTU_04' => __('GTU_04: tobacco products, dried tobacco, e-cigarette liquid', 'polski'),
            'GTU_05' => __('GTU_05: waste', 'polski'),
            'GTU_06' => __('GTU_06: electronic devices, their parts and materials', 'polski'),
            'GTU_07' => __('GTU_07: vehicles and vehicle parts', 'polski'),
            'GTU_08' => __('GTU_08: precious and base metals', 'polski'),
            'GTU_09' => __('GTU_09: medicines, medical devices, foodstuffs for particular nutritional uses', 'polski'),
            'GTU_10' => __('GTU_10: buildings, structures and land', 'polski'),
            'GTU_11' => __('GTU_11: transfer of greenhouse gas emission allowances', 'polski'),
            'GTU_12' => __('GTU_12: intangible services, e.g. advisory, accounting, legal, marketing', 'polski'),
            'GTU_13' => __('GTU_13: transport and warehouse management services', 'polski'),
        ];
    }

    /**
     * Options for the product select: no marking, then the thirteen codes.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return ['' => __('- No GTU marking -', 'polski')] + self::codes();
    }

    /**
     * The code stored on a product, or an empty string.
     *
     * Anything that is not one of the thirteen is read as no marking rather
     * than printed, because a made-up code on an invoice is worse than none:
     * FA(2) rejects the document and an accountant trusts what is on the page.
     */
    public static function forProduct(int $productId): string
    {
        if ($productId <= 0) {
            return '';
        }

        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product) {
            return '';
        }

        $code = (string) $product->get_meta(self::META, true);

        return isset(self::codes()[$code]) ? $code : '';
    }
}
