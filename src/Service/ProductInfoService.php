<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;

/**
 * Product information display: manufacturer, GPSR, safety docs, power supply, defect description.
 */
final class ProductInfoService
{
    /**
     * Per-request memo for getManufacturer(). The single product page calls
     * this through getManufacturerHtml() and, on stores with structured-data
     * enabled, again through the schema callback - same product ID, same
     * answer. WP core does cache term lookups but the function-call chain
     * (get_the_terms -> ... -> filters) costs ~0.05 ms each, which adds up
     * across a loop with many products.
     *
     * @var array<int, string>
     */
    private array $manufacturerCache = [];

    /**
     * @var array<int, list<\WP_Term>>
     */
    private array $brandTermsCache = [];

    /**
     * Get manufacturer name for a product.
     */
    public function getManufacturer(\WC_Product $product): string
    {
        $productId = $product->get_id();

        if (isset($this->manufacturerCache[$productId])) {
            return $this->manufacturerCache[$productId];
        }

        $terms = get_the_terms($productId, 'polski_manufacturer');

        $this->manufacturerCache[$productId] = is_array($terms) && ! empty($terms)
            ? (string) $terms[0]->name
            : '';

        return $this->manufacturerCache[$productId];
    }

    /**
     * Get manufacturer HTML for display.
     */
    public function getManufacturerHtml(\WC_Product $product): string
    {
        if (! ModulesPage::isModuleEnabled('manufacturer')) {
            return '';
        }

        $name = $this->getManufacturer($product);

        if ($name === '') {
            return '';
        }

        return sprintf(
            '<div class="polski-manufacturer"><span class="polski-manufacturer__label">%s:</span> <span class="polski-manufacturer__name">%s</span></div>',
            esc_html__('Manufacturer', 'polski'),
            esc_html($name),
        );
    }

    /**
     * Get brand names for a product.
     *
     * @return list<string>
     */
    public function getBrands(\WC_Product $product): array
    {
        $terms = $this->getBrandTerms($product);

        if ($terms === []) {
            return [];
        }

        $brands = [];

        foreach ($terms as $term) {
            $brands[] = $term->name;
        }

        return $brands;
    }

    /**
     * Get brand terms for a product.
     *
     * @return list<\WP_Term>
     */
    public function getBrandTerms(\WC_Product $product): array
    {
        $productId = $product->get_id();

        if (isset($this->brandTermsCache[$productId])) {
            return $this->brandTermsCache[$productId];
        }

        $terms = get_the_terms($productId, 'polski_brand');

        $this->brandTermsCache[$productId] = is_array($terms) && $terms !== []
            ? array_values($terms)
            : [];

        return $this->brandTermsCache[$productId];
    }

    /**
     * Get brand HTML for display.
     */
    public function getBrandHtml(\WC_Product $product): string
    {
        if (! ModulesPage::isModuleEnabled('brands')) {
            return '';
        }

        $terms = $this->getBrandTerms($product);

        if ($terms === []) {
            return '';
        }

        $settings = get_option('polski_brand', []);
        $label = is_array($settings) ? (string) ($settings['label'] ?? __('Brand', 'polski')) : __('Brand', 'polski');
        $showLabel = ! is_array($settings) || (bool) ($settings['show_label'] ?? true);
        $separator = is_array($settings) ? (string) ($settings['separator'] ?? ', ') : ', ';
        $linkTerms = ! is_array($settings) || (bool) ($settings['link_terms'] ?? true);
        $brandItems = [];

        foreach ($terms as $term) {
            $termName = esc_html($term->name);

            if ($linkTerms) {
                $termLink = get_term_link($term);

                if (! is_wp_error($termLink)) {
                    $brandItems[] = sprintf(
                        '<a href="%s" class="polski-brand__link">%s</a>',
                        esc_url($termLink),
                        $termName,
                    );
                    continue;
                }
            }

            $brandItems[] = sprintf(
                '<span class="polski-brand__term">%s</span>',
                $termName,
            );
        }

        return sprintf(
            '<div class="polski-brand">%s<span class="polski-brand__name">%s</span></div>',
            $showLabel ? sprintf('<span class="polski-brand__label">%s:</span> ', esc_html($label)) : '',
            implode(esc_html($separator), $brandItems),
        );
    }

    /**
     * Get GPSR responsible person info.
     */
    public function getGPSRResponsible(\WC_Product $product): string
    {
        return (string) $product->get_meta('_polski_gpsr_responsible', true);
    }

    /**
     * Build the combined safety summary HTML (GPSR responsible person, safety
     * documents, power supply, defect description). Returns an empty string
     * when no safety data is present so callers can skip empty wrappers.
     */
    public function getSafetyInfoHtml(\WC_Product $product): string
    {
        $parts = array_filter([
            $this->getSafetyDocumentsHtml($product),
            $this->getPowerSupplyHtml($product),
            $this->getDefectDescriptionHtml($product),
        ]);

        $gpsr = $this->getGPSRResponsible($product);

        if ($gpsr !== '') {
            array_unshift($parts, sprintf(
                '<div class="polski-gpsr"><span class="polski-gpsr__label">%s:</span> %s</div>',
                esc_html__('Osoba odpowiedzialna (GPSR)', 'polski'),
                esc_html($gpsr),
            ));
        }

        if ($parts === []) {
            return '';
        }

        return '<div class="polski-safety">' . implode('', $parts) . '</div>';
    }

    /**
     * Get safety document attachment IDs.
     *
     * @return list<int>
     */
    public function getSafetyDocuments(\WC_Product $product): array
    {
        $raw = $product->get_meta('_polski_safety_docs', true);

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? array_values(array_map('intval', $decoded)) : [];
        }

        return is_array($raw) ? array_values(array_map('intval', $raw)) : [];
    }

    /**
     * Get safety documents HTML (list of download links).
     */
    public function getSafetyDocumentsHtml(\WC_Product $product): string
    {
        if (! ModulesPage::isModuleEnabled('gpsr')) {
            return '';
        }

        $docIds = $this->getSafetyDocuments($product);

        if (empty($docIds)) {
            return '';
        }

        $links = [];
        foreach ($docIds as $attachmentId) {
            $url = wp_get_attachment_url($attachmentId);
            $title = get_the_title($attachmentId);

            if ($url) {
                $links[] = sprintf(
                    '<a href="%s" target="_blank" rel="noopener">%s</a>',
                    esc_url($url),
                    esc_html($title ?: __('Safety Document', 'polski')),
                );
            }
        }

        if (empty($links)) {
            return '';
        }

        return sprintf(
            '<div class="polski-safety-docs"><span class="polski-safety-docs__label">%s:</span><ul>%s</ul></div>',
            esc_html__('Safety Documents', 'polski'),
            '<li>' . implode('</li><li>', $links) . '</li>',
        );
    }

    /**
     * Get safety instructions text.
     */
    public function getSafetyInstructions(\WC_Product $product): string
    {
        return (string) $product->get_meta('_polski_safety_instructions', true);
    }

    /**
     * Get power supply information.
     */
    public function getPowerSupply(\WC_Product $product): string
    {
        return (string) $product->get_meta('_polski_power_supply', true);
    }

    /**
     * Get power supply HTML.
     */
    public function getPowerSupplyHtml(\WC_Product $product): string
    {
        if (! ModulesPage::isModuleEnabled('power_supply')) {
            return '';
        }

        $info = $this->getPowerSupply($product);

        if ($info === '') {
            return '';
        }

        return sprintf(
            '<div class="polski-power-supply"><span class="polski-power-supply__label">%s:</span> <span>%s</span></div>',
            esc_html__('Power Supply', 'polski'),
            esc_html($info),
        );
    }

    /**
     * Get defect description.
     */
    public function getDefectDescription(\WC_Product $product): string
    {
        return (string) $product->get_meta('_polski_defect_description', true);
    }

    /**
     * Get defect description HTML.
     */
    public function getDefectDescriptionHtml(\WC_Product $product): string
    {
        if (! ModulesPage::isModuleEnabled('gpsr')) {
            return '';
        }

        $desc = $this->getDefectDescription($product);

        if ($desc === '') {
            return '';
        }

        return sprintf(
            '<div class="polski-defect-description"><span class="polski-defect-description__label">%s:</span> <span>%s</span></div>',
            esc_html__('Defect Description', 'polski'),
            esc_html($desc),
        );
    }

    /**
     * Get GTIN/EAN code.
     */
    public function getGTIN(\WC_Product $product): string
    {
        // Try WooCommerce native GTIN first (WC 8.4+).
        $gtin = $product->get_global_unique_id();

        if ($gtin !== '') {
            return $gtin;
        }

        return (string) $product->get_meta('_polski_gtin', true);
    }
}
