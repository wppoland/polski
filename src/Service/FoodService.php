<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;
/**
 * Food product module: nutrients, allergens, ingredients, Nutri-Score, etc.
 *
 * This module can be enabled/disabled via polski_food settings.
 */
final class FoodService
{
    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        $settings = get_option('polski_food', []);

        return is_array($settings) ? $settings : [];
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->getSettings()['enabled'] ?? false);
    }

    /**
     * Get ingredients text.
     */
    public function getIngredients(\WC_Product $product): string
    {
        return (string) $product->get_meta('_polski_ingredients', true);
    }

    /**
     * Get ingredients HTML.
     */
    public function getIngredientsHtml(\WC_Product $product): string
    {
        if (! (bool) ($this->getSettings()['show_ingredients'] ?? true)) {
            return '';
        }

        $ingredients = $this->getIngredients($product);

        if ($ingredients === '') {
            return '';
        }

        return sprintf(
            '<div class="polski-ingredients"><span class="polski-ingredients__label">%s:</span> <span>%s</span></div>',
            esc_html((string) ($this->getSettings()['ingredients_label'] ?? __('Ingredients', 'polski'))),
            esc_html($ingredients),
        );
    }

    /**
     * Get allergen term names for a product.
     *
     * @return list<string>
     */
    public function getAllergens(\WC_Product $product): array
    {
        $terms = get_the_terms($product->get_id(), 'polski_allergen');

        if (! is_array($terms)) {
            return [];
        }

        return array_map(static fn (\WP_Term $t) => $t->name, $terms);
    }

    /**
     * Get allergens HTML.
     */
    public function getAllergensHtml(\WC_Product $product): string
    {
        if (! (bool) ($this->getSettings()['show_allergens'] ?? true)) {
            return '';
        }

        $allergens = $this->getAllergens($product);

        if (empty($allergens)) {
            return '';
        }

        return sprintf(
            '<div class="polski-allergens"><span class="polski-allergens__label">%s:</span> <strong>%s</strong></div>',
            esc_html((string) ($this->getSettings()['allergens_label'] ?? __('Allergens', 'polski'))),
            esc_html(implode(', ', $allergens)),
        );
    }

    /**
     * Get nutrients data.
     *
     * @return array<string, array{value: float, unit: string}>
     */
    public function getNutrients(\WC_Product $product): array
    {
        $raw = $product->get_meta('_polski_nutrients', true);

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * Get nutrients table HTML.
     */
    public function getNutrientsHtml(\WC_Product $product): string
    {
        if (! (bool) ($this->getSettings()['show_nutrients'] ?? true)) {
            return '';
        }

        $nutrients = $this->getNutrients($product);

        if (empty($nutrients)) {
            return '';
        }

        $referenceUnit = (string) $product->get_meta('_polski_nutrient_reference_unit', true);

        if ($referenceUnit === '') {
            $referenceUnit = (string) ($this->getSettings()['nutrients_reference_unit'] ?? __('100 g', 'polski'));
        }

        $rows = '';

        foreach ($nutrients as $name => $data) {
            $value = is_array($data) ? ($data['value'] ?? '') : $data;
            $unit = is_array($data) ? ($data['unit'] ?? '') : '';

            $rows .= sprintf(
                '<tr><td>%s</td><td>%s %s</td></tr>',
                esc_html($name),
                esc_html((string) $value),
                esc_html($unit),
            );
        }

        return sprintf(
            '<div class="polski-nutrients">
                <table class="polski-nutrients__table">
                    <caption>%s %s</caption>
                    <thead><tr><th>%s</th><th>%s</th></tr></thead>
                    <tbody>%s</tbody>
                </table>
            </div>',
            esc_html((string) ($this->getSettings()['nutrients_caption_prefix'] ?? __('Nutrition facts per', 'polski'))),
            esc_html($referenceUnit),
            esc_html((string) ($this->getSettings()['nutrients_column_name'] ?? __('Nutrient', 'polski'))),
            esc_html((string) ($this->getSettings()['nutrients_column_value'] ?? __('Value', 'polski'))),
            $rows,
        );
    }

    /**
     * Get Nutri-Score grade (A-E).
     */
    public function getNutriScore(\WC_Product $product): string
    {
        $score = (string) $product->get_meta('_polski_nutri_score', true);

        if (! in_array($score, ['A', 'B', 'C', 'D', 'E'], true)) {
            return '';
        }

        return $score;
    }

    /**
     * Get Nutri-Score HTML badge.
     */
    public function getNutriScoreHtml(\WC_Product $product): string
    {
        if (! (bool) ($this->getSettings()['show_nutri_score'] ?? true)) {
            return '';
        }

        $score = $this->getNutriScore($product);

        if ($score === '') {
            return '';
        }

        return sprintf(
            '<div class="polski-nutri-score polski-nutri-score--%s">
                <span class="polski-nutri-score__label">%s:</span>
                <span class="polski-nutri-score__grade">%s</span>
            </div>',
            esc_attr(strtolower($score)),
            esc_html((string) ($this->getSettings()['nutri_score_label'] ?? __('Nutri-Score', 'polski'))),
            esc_html($score),
        );
    }

    /**
     * Get net filling quantity.
     */
    public function getNetFillingQuantity(\WC_Product $product): string
    {
        return (string) $product->get_meta('_polski_net_filling_quantity', true);
    }

    /**
     * Get alcohol content.
     */
    public function getAlcoholContent(\WC_Product $product): string
    {
        return (string) $product->get_meta('_polski_alcohol_content', true);
    }

    /**
     * Get alcohol content HTML.
     */
    public function getAlcoholContentHtml(\WC_Product $product): string
    {
        if (! (bool) ($this->getSettings()['show_alcohol'] ?? true)) {
            return '';
        }

        $content = $this->getAlcoholContent($product);

        if ($content === '') {
            return '';
        }

        return sprintf(
            '<div class="polski-alcohol"><span class="polski-alcohol__label">%s:</span> %s%s</div>',
            esc_html((string) ($this->getSettings()['alcohol_label'] ?? __('Alcohol content', 'polski'))),
            esc_html($content),
            esc_html((string) ($this->getSettings()['alcohol_suffix'] ?? __('% vol.', 'polski'))),
        );
    }

    /**
     * Get place of origin.
     */
    public function getPlaceOfOrigin(\WC_Product $product): string
    {
        return (string) $product->get_meta('_polski_place_of_origin', true);
    }

    /**
     * Get food distributor.
     */
    public function getDistributor(\WC_Product $product): string
    {
        return (string) $product->get_meta('_polski_food_distributor', true);
    }

    /**
     * Get complete food info HTML block.
     */
    public function getFoodInfoHtml(\WC_Product $product): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        $parts = array_filter([
            $this->getIngredientsHtml($product),
            $this->getAllergensHtml($product),
            $this->getNutrientsHtml($product),
            $this->getNutriScoreHtml($product),
            $this->getAlcoholContentHtml($product),
        ]);

        $origin = $this->getPlaceOfOrigin($product);
        $distributor = $this->getDistributor($product);
        $netFilling = $this->getNetFillingQuantity($product);

        if ((bool) ($this->getSettings()['show_origin'] ?? true) && $origin !== '') {
            $parts[] = sprintf(
                '<div class="polski-origin"><span class="polski-origin__label">%s:</span> %s</div>',
                esc_html((string) ($this->getSettings()['origin_label'] ?? __('Country of origin', 'polski'))),
                esc_html($origin),
            );
        }

        if ((bool) ($this->getSettings()['show_distributor'] ?? true) && $distributor !== '') {
            $parts[] = sprintf(
                '<div class="polski-distributor"><span class="polski-distributor__label">%s:</span> %s</div>',
                esc_html((string) ($this->getSettings()['distributor_label'] ?? __('Distributor', 'polski'))),
                esc_html($distributor),
            );
        }

        if ((bool) ($this->getSettings()['show_net_filling'] ?? true) && $netFilling !== '') {
            $parts[] = sprintf(
                '<div class="polski-net-filling"><span class="polski-net-filling__label">%s:</span> %s</div>',
                esc_html((string) ($this->getSettings()['net_filling_label'] ?? __('Net content', 'polski'))),
                esc_html($netFilling),
            );
        }

        if (empty($parts)) {
            return '';
        }

        return '<div class="polski-food-info">' . implode('', $parts) . '</div>';
    }
}
