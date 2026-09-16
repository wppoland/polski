<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Two markings a Polish shop has to carry on the product page itself.
 *
 * Medical device: art. 7 of Regulation (EU) 2017/745 forbids presentation that
 * misleads about a product's medical purpose, and the Polish Medical Devices Act
 * requires advertising aimed at the public to be identifiable as such. A listing
 * that never says "wyrob medyczny" leaves the buyer to guess.
 *
 * Adults only: alcohol, tobacco and similar goods may not be presented as if
 * anyone can buy them. The notice is the minimum; an age gate is a separate
 * decision for the shop.
 *
 * Both are per-product flags because a shop rarely sells only one kind.
 */
final class ProductMarkingsService implements HasHooks
{
    public const META_MEDICAL_DEVICE = '_polski_is_medical_device';
    public const META_ADULTS_ONLY = '_polski_is_adults_only';

    private const OPTION = 'polski_product_markings';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('product_markings')) {
            return;
        }

        // After the price, before the excerpt: the buyer should read it with the
        // offer, not below the add-to-cart button.
        add_action('woocommerce_single_product_summary', [$this, 'renderNotices'], 11);
    }

    public function renderNotices(): void
    {
        $product = wc_get_product();

        if (! $product instanceof \WC_Product) {
            return;
        }

        $notices = [];

        $settings = get_option(self::OPTION, []);
        $settings = is_array($settings) ? $settings : [];

        if ($this->isFlagged($product, self::META_MEDICAL_DEVICE)) {
            // Keys spelled out, not composed, so the release check that pairs a
            // declared setting with a reader can see them.
            $notices[] = [
                'medical',
                $this->text(
                    (string) ($settings['medical_device_notice'] ?? ''),
                    __('This is a medical device. Read the instructions for use and the label, or consult a doctor or pharmacist, as every medical device carries a risk when used other than as intended.', 'polski'),
                ),
            ];
        }

        if ($this->isFlagged($product, self::META_ADULTS_ONLY)) {
            $notices[] = [
                'adults',
                $this->text(
                    (string) ($settings['adults_only_notice'] ?? ''),
                    __('Sold to adults only. Age is verified on delivery, and the order is not handed over without it.', 'polski'),
                ),
            ];
        }

        if ($notices === []) {
            return;
        }

        echo '<div class="polski-product-markings">';

        foreach ($notices as [$kind, $text]) {
            printf(
                '<p class="polski-product-markings__notice polski-product-markings__notice--%1$s">%2$s</p>',
                esc_attr($kind),
                esc_html($text),
            );
        }

        echo '</div>';
    }

    /**
     * A variation inherits the parent's marking: the medical purpose does not
     * change with the size of the box.
     */
    private function isFlagged(\WC_Product $product, string $meta): bool
    {
        if ($product->get_meta($meta, true) === 'yes') {
            return true;
        }

        $parentId = $product->get_parent_id();

        if ($parentId > 0) {
            $parent = wc_get_product($parentId);

            if ($parent instanceof \WC_Product) {
                return $parent->get_meta($meta, true) === 'yes';
            }
        }

        return false;
    }

    private function text(string $configured, string $default): string
    {
        $configured = trim($configured);

        return $configured !== '' ? $configured : $default;
    }
}
