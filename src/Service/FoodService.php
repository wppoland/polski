<?php

declare(strict_types=1);

namespace Spolszczony\Service;

/**
 * Food product module: nutrients, allergens, ingredients, Nutri-Score, etc.
 *
 * This module can be enabled/disabled via spolszczony_food settings.
 */
final class FoodService
{
    public function isEnabled(): bool
    {
        $settings = get_option('spolszczony_food', []);
        return is_array($settings) && (bool) ($settings['enabled'] ?? false);
    }

    /**
     * Get ingredients text.
     */
    public function getIngredients(\WC_Product $product): string
    {
        return (string) $product->get_meta('_spolszczony_ingredients', true);
    }

    /**
     * Get ingredients HTML.
     */
    public function getIngredientsHtml(\WC_Product $product): string
    {
        $ingredients = $this->getIngredients($product);

        if ($ingredients === '') {
            return '';
        }

        return sprintf(
            '<div class="spolszczony-ingredients"><span class="spolszczony-ingredients__label">%s:</span> <span>%s</span></div>',
            esc_html__('Ingredients', 'spolszczony'),
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
        $terms = get_the_terms($product->get_id(), 'spolszczony_allergen');

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
        $allergens = $this->getAllergens($product);

        if (empty($allergens)) {
            return '';
        }

        return sprintf(
            '<div class="spolszczony-allergens"><span class="spolszczony-allergens__label">%s:</span> <strong>%s</strong></div>',
            esc_html__('Allergens', 'spolszczony'),
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
        $raw = $product->get_meta('_spolszczony_nutrients', true);

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
        $nutrients = $this->getNutrients($product);

        if (empty($nutrients)) {
            return '';
        }

        $referenceUnit = (string) $product->get_meta('_spolszczony_nutrient_reference_unit', true);
        if ($referenceUnit === '') {
            $referenceUnit = __('100 g', 'spolszczony');
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
            '<div class="spolszczony-nutrients">
                <table class="spolszczony-nutrients__table">
                    <caption>%s %s</caption>
                    <thead><tr><th>%s</th><th>%s</th></tr></thead>
                    <tbody>%s</tbody>
                </table>
            </div>',
            esc_html__('Nutritional values per', 'spolszczony'),
            esc_html($referenceUnit),
            esc_html__('Nutrient', 'spolszczony'),
            esc_html__('Value', 'spolszczony'),
            $rows,
        );
    }

    /**
     * Get Nutri-Score grade (A-E).
     */
    public function getNutriScore(\WC_Product $product): string
    {
        $score = (string) $product->get_meta('_spolszczony_nutri_score', true);

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
        $score = $this->getNutriScore($product);

        if ($score === '') {
            return '';
        }

        return sprintf(
            '<div class="spolszczony-nutri-score spolszczony-nutri-score--%s">
                <span class="spolszczony-nutri-score__label">Nutri-Score:</span>
                <span class="spolszczony-nutri-score__grade">%s</span>
            </div>',
            esc_attr(strtolower($score)),
            esc_html($score),
        );
    }

    /**
     * Get net filling quantity.
     */
    public function getNetFillingQuantity(\WC_Product $product): string
    {
        return (string) $product->get_meta('_spolszczony_net_filling_quantity', true);
    }

    /**
     * Get alcohol content.
     */
    public function getAlcoholContent(\WC_Product $product): string
    {
        return (string) $product->get_meta('_spolszczony_alcohol_content', true);
    }

    /**
     * Get alcohol content HTML.
     */
    public function getAlcoholContentHtml(\WC_Product $product): string
    {
        $content = $this->getAlcoholContent($product);

        if ($content === '') {
            return '';
        }

        return sprintf(
            '<div class="spolszczony-alcohol"><span class="spolszczony-alcohol__label">%s:</span> %s%% vol.</div>',
            esc_html__('Alcohol content', 'spolszczony'),
            esc_html($content),
        );
    }

    /**
     * Get place of origin.
     */
    public function getPlaceOfOrigin(\WC_Product $product): string
    {
        return (string) $product->get_meta('_spolszczony_place_of_origin', true);
    }

    /**
     * Get food distributor.
     */
    public function getDistributor(\WC_Product $product): string
    {
        return (string) $product->get_meta('_spolszczony_food_distributor', true);
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

        if (empty($parts)) {
            return '';
        }

        $origin = $this->getPlaceOfOrigin($product);
        $distributor = $this->getDistributor($product);
        $netFilling = $this->getNetFillingQuantity($product);

        if ($origin !== '') {
            $parts[] = sprintf(
                '<div class="spolszczony-origin"><span class="spolszczony-origin__label">%s:</span> %s</div>',
                esc_html__('Place of origin', 'spolszczony'),
                esc_html($origin),
            );
        }

        if ($distributor !== '') {
            $parts[] = sprintf(
                '<div class="spolszczony-distributor"><span class="spolszczony-distributor__label">%s:</span> %s</div>',
                esc_html__('Distributor', 'spolszczony'),
                esc_html($distributor),
            );
        }

        if ($netFilling !== '') {
            $parts[] = sprintf(
                '<div class="spolszczony-net-filling"><span class="spolszczony-net-filling__label">%s:</span> %s</div>',
                esc_html__('Net content', 'spolszczony'),
                esc_html($netFilling),
            );
        }

        return '<div class="spolszczony-food-info">' . implode('', $parts) . '</div>';
    }
}
