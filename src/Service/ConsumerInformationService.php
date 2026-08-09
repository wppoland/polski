<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;

/**
 * Pre-contractual consumer information required by Directive (EU) 2024/825,
 * which applies from 27 September 2026.
 *
 * The directive amends the Consumer Rights Directive with four product-page
 * duties that WooCommerce does not cover on its own:
 *  - a reminder that the legal guarantee of conformity exists (store-wide, so
 *    it is a setting, not a product field);
 *  - the length of a commercial guarantee of durability, where the producer
 *    offers one longer than the statutory period;
 *  - how long free software updates are supplied, for goods with digital
 *    elements;
 *  - repair information.
 *
 * The exact harmonised label for the durability guarantee comes from an
 * implementing act. This module deliberately renders plain labelled rows rather
 * than guessing at that artwork, so a shop is informative today and can adopt
 * the official mark when it is published.
 */
final class ConsumerInformationService
{
    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        $settings = get_option('polski_consumer_info', []);

        return is_array($settings) ? $settings : [];
    }

    /**
     * Store-wide reminder that the legal guarantee of conformity applies.
     */
    public function getLegalGuaranteeText(): string
    {
        $text = (string) ($this->getSettings()['legal_guarantee_text'] ?? '');

        return $text !== '' ? $text : self::defaultLegalGuaranteeText();
    }

    public static function defaultLegalGuaranteeText(): string
    {
        return __(
            'Goods sold in this shop are covered by the statutory guarantee of conformity. If the goods do not conform to the contract, you have the remedies provided by law, free of charge.',
            'polski',
        );
    }

    /**
     * Commercial guarantee of durability, in months. Zero means none declared.
     */
    public function getDurabilityMonths(\WC_Product $product): int
    {
        return max(0, (int) $product->get_meta('_polski_durability_guarantee_months', true));
    }

    /**
     * Period of free software updates, in months. Zero means none declared.
     */
    public function getUpdateMonths(\WC_Product $product): int
    {
        return max(0, (int) $product->get_meta('_polski_update_period_months', true));
    }

    public function getRepairInfo(\WC_Product $product): string
    {
        return (string) $product->get_meta('_polski_repair_info', true);
    }

    /**
     * Render the consumer information block for a single product.
     *
     * Returns an empty string when the module is off, so the service is safe to
     * boot unconditionally like the rest of the family.
     */
    public function getHtml(\WC_Product $product): string
    {
        if (! ModulesPage::isModuleEnabled('consumer_information')) {
            return '';
        }

        $rows = '';

        $durability = $this->getDurabilityMonths($product);
        if ($durability > 0) {
            $rows .= $this->row(
                __('Guarantee of durability', 'polski'),
                sprintf(
                    /* translators: %d: number of months. */
                    _n('%d month', '%d months', $durability, 'polski'),
                    $durability,
                ),
            );
        }

        $updates = $this->getUpdateMonths($product);
        if ($updates > 0) {
            $rows .= $this->row(
                __('Free software updates', 'polski'),
                sprintf(
                    /* translators: %d: number of months. */
                    _n('%d month', '%d months', $updates, 'polski'),
                    $updates,
                ),
            );
        }

        $repair = $this->getRepairInfo($product);
        if ($repair !== '') {
            $rows .= $this->row(__('Repair information', 'polski'), $repair);
        }

        $notice = (bool) ($this->getSettings()['show_legal_guarantee'] ?? true)
            ? sprintf(
                '<p class="polski-consumer-info__legal">%s</p>',
                esc_html($this->getLegalGuaranteeText()),
            )
            : '';

        if ($rows === '' && $notice === '') {
            return '';
        }

        return sprintf(
            '<div class="polski-consumer-info">%s%s</div>',
            $notice,
            $rows !== '' ? '<dl class="polski-consumer-info__list">' . $rows . '</dl>' : '',
        );
    }

    private function row(string $label, string $value): string
    {
        return sprintf(
            '<dt class="polski-consumer-info__label">%s</dt><dd class="polski-consumer-info__value">%s</dd>',
            esc_html($label),
            esc_html($value),
        );
    }
}
