<?php

declare(strict_types=1);

namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Keep My Account > Addresses from showing the same field twice.
 *
 * Several modules register a field two ways on purpose: the classic
 * `woocommerce_billing_fields` filter, which is the only one the shortcode
 * checkout renders, and WooCommerce's additional-fields API, which is the only
 * one the block checkout renders. WooCommerce has no way to scope an `address`
 * field to the block checkout, and it puts those on the edit-address form
 * itself (`woocommerce_address_to_edit`), so that one screen received both
 * copies. The additional-fields copy is dropped there; the classic one stays,
 * because that is what our scripts bind to and what the rest of the plugin
 * reads.
 */
final class AddressFormFieldDedupe implements HasHooks
{
    public function registerHooks(): void
    {
        // After WooCommerce, which adds the additional fields at priority 10.
        add_filter('woocommerce_address_to_edit', [$this, 'dropAdditionalFieldTwins'], 20, 2);
    }

    /**
     * @param array<string, array<string, mixed>>|mixed $address
     * @return array<string, array<string, mixed>>|mixed
     */
    public function dropAdditionalFieldTwins(mixed $address, mixed $addressType = 'billing'): mixed
    {
        if (! is_array($address) || ! is_string($addressType)) {
            return $address;
        }

        foreach (array_keys($address) as $key) {
            if (! is_string($key) || ! preg_match('#^_wc_[a-z]+/polski/(.+)$#', $key, $match)) {
                continue;
            }

            // The group prefix is WooCommerce's to choose, so the twin is looked
            // up by field id: `polski/regon` against `billing_regon`.
            foreach ([$addressType . '_' . $match[1], 'polski_' . $match[1]] as $twin) {
                if (isset($address[$twin])) {
                    unset($address[$key]);
                    break;
                }
            }
        }

        return $address;
    }
}
