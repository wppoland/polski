<?php

declare(strict_types=1);

namespace Spolszczony\Service;

/**
 * Product information display: manufacturer, GPSR, safety docs, power supply, defect description.
 */
final class ProductInfoService
{
    /**
     * Get manufacturer name for a product.
     */
    public function getManufacturer(\WC_Product $product): string
    {
        $terms = get_the_terms($product->get_id(), 'spolszczony_manufacturer');

        if (is_array($terms) && ! empty($terms)) {
            return $terms[0]->name;
        }

        return '';
    }

    /**
     * Get manufacturer HTML for display.
     */
    public function getManufacturerHtml(\WC_Product $product): string
    {
        $name = $this->getManufacturer($product);

        if ($name === '') {
            return '';
        }

        return sprintf(
            '<div class="spolszczony-manufacturer"><span class="spolszczony-manufacturer__label">%s:</span> <span class="spolszczony-manufacturer__name">%s</span></div>',
            esc_html__('Manufacturer', 'spolszczony'),
            esc_html($name),
        );
    }

    /**
     * Get GPSR responsible person info.
     */
    public function getGPSRResponsible(\WC_Product $product): string
    {
        return (string) $product->get_meta('_spolszczony_gpsr_responsible', true);
    }

    /**
     * Get safety document attachment IDs.
     *
     * @return list<int>
     */
    public function getSafetyDocuments(\WC_Product $product): array
    {
        $raw = $product->get_meta('_spolszczony_safety_docs', true);

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? array_map('intval', $decoded) : [];
        }

        return is_array($raw) ? array_map('intval', $raw) : [];
    }

    /**
     * Get safety documents HTML (list of download links).
     */
    public function getSafetyDocumentsHtml(\WC_Product $product): string
    {
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
                    esc_html($title ?: __('Safety Document', 'spolszczony')),
                );
            }
        }

        if (empty($links)) {
            return '';
        }

        return sprintf(
            '<div class="spolszczony-safety-docs"><span class="spolszczony-safety-docs__label">%s:</span><ul>%s</ul></div>',
            esc_html__('Safety Documents', 'spolszczony'),
            '<li>' . implode('</li><li>', $links) . '</li>',
        );
    }

    /**
     * Get safety instructions text.
     */
    public function getSafetyInstructions(\WC_Product $product): string
    {
        return (string) $product->get_meta('_spolszczony_safety_instructions', true);
    }

    /**
     * Get power supply information.
     */
    public function getPowerSupply(\WC_Product $product): string
    {
        return (string) $product->get_meta('_spolszczony_power_supply', true);
    }

    /**
     * Get power supply HTML.
     */
    public function getPowerSupplyHtml(\WC_Product $product): string
    {
        $info = $this->getPowerSupply($product);

        if ($info === '') {
            return '';
        }

        return sprintf(
            '<div class="spolszczony-power-supply"><span class="spolszczony-power-supply__label">%s:</span> <span>%s</span></div>',
            esc_html__('Power Supply', 'spolszczony'),
            esc_html($info),
        );
    }

    /**
     * Get defect description.
     */
    public function getDefectDescription(\WC_Product $product): string
    {
        return (string) $product->get_meta('_spolszczony_defect_description', true);
    }

    /**
     * Get defect description HTML.
     */
    public function getDefectDescriptionHtml(\WC_Product $product): string
    {
        $desc = $this->getDefectDescription($product);

        if ($desc === '') {
            return '';
        }

        return sprintf(
            '<div class="spolszczony-defect-description"><span class="spolszczony-defect-description__label">%s:</span> <span>%s</span></div>',
            esc_html__('Defect Description', 'spolszczony'),
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

        return (string) $product->get_meta('_spolszczony_gtin', true);
    }
}
