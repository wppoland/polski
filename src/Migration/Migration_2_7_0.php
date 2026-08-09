<?php

declare(strict_types=1);

namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;

/**
 * Switch the Food module on for shops that are already using it.
 *
 * FoodService::isEnabled() used to read polski_food['enabled'], which
 * config/defaults.php seeds as false and which nothing in the plugin ever
 * writes. The product page block was therefore invisible on every shop, no
 * matter what the Food module toggle said, while the shortcodes and the
 * Elementor widgets bypassed that check and rendered regardless of both.
 *
 * isEnabled() now reads the module, which is the switch a merchant can actually
 * reach, and the individual render methods honour it too. That fixes the product
 * page but would take the shortcodes and widgets away from a shop that is using
 * them with the module off, so switch the module on wherever food data exists.
 *
 * Allergen declarations are a Regulation (EU) 1169/2011 obligation, so this
 * deliberately errs towards enabling: any one signal is enough, and a shop that
 * ends up with the module on can simply switch it off.
 */
final class Migration_2_7_0 implements Migration
{
    public const VERSION = '2.7.0';

    /** Product meta keys that mean somebody entered food data. */
    private const FOOD_META = [
        '_polski_ingredients',
        '_polski_nutrients',
        '_polski_nutri_score',
        '_polski_alcohol_content',
        '_polski_place_of_origin',
        '_polski_net_filling_quantity',
    ];

    public function run(): void
    {
        $modules = get_option('polski_modules', []);

        if (! is_array($modules)) {
            $modules = [];
        }

        $changed = false;

        if (! (bool) ($modules['food_module'] ?? false) && $this->foodDataExists()) {
            $modules['food_module'] = true;
            $changed = true;
        }

        // Double opt-in used to run on polski_doi['enabled'] alone. Now that the
        // module has to agree, a store with the setting on and the module at its
        // default off would stop blocking unverified logins the moment this
        // release lands. Carry the existing decision across.
        if (! (bool) ($modules['double_opt_in'] ?? false) && $this->doubleOptInWasOn()) {
            $modules['double_opt_in'] = true;
            $changed = true;
        }

        // Same for the environmental claim fields: the product panel stops
        // rendering them when the module is off, so switch it on wherever a
        // product already carries one, or the next product save would blank it.
        if (! (bool) ($modules['green_claims'] ?? false) && $this->greenClaimsExist()) {
            $modules['green_claims'] = true;
            $changed = true;
        }

        if ($changed) {
            update_option('polski_modules', $modules);
        }
    }

    private function doubleOptInWasOn(): bool
    {
        $doi = get_option('polski_doi', []);

        return is_array($doi) && (bool) ($doi['enabled'] ?? false);
    }

    private function greenClaimsExist(): bool
    {
        global $wpdb;

        foreach (['_polski_green_claim_basis', '_polski_green_claim_cert_url', '_polski_green_claim_expiry'] as $metaKey) {
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != '' LIMIT 1",
                    $metaKey,
                ),
            );

            if ($count > 0) {
                return true;
            }
        }

        return false;
    }

    private function foodDataExists(): bool
    {
        // A shop that had reached into the option by hand to make the block
        // appear counts as using the module, even with no product data yet.
        $food = get_option('polski_food', []);

        if (is_array($food) && (bool) ($food['enabled'] ?? false)) {
            return true;
        }

        global $wpdb;

        // One bound query per key rather than a built IN list. Six short counts
        // that stop at the first hit beat a dynamically assembled placeholder
        // string that no static analyser can read.
        foreach (self::FOOD_META as $metaKey) {
            $withMeta = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != '' LIMIT 1",
                    $metaKey,
                ),
            );

            if ($withMeta > 0) {
                return true;
            }
        }

        $tagged = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s AND count > 0",
                'polski_allergen',
            ),
        );

        return $tagged > 0;
    }
}
