<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\AI\AiClient;
use WC_Product;

/**
 * AI-assisted, draft-only product summary generator.
 *
 * Produces a short FACTUAL summary for a WooCommerce product by delegating to
 * the WordPress 7.0+ AI Client through the Polski\AI\AiClient facade. The plugin
 * never stores a provider key and never makes an outbound HTTP request itself -
 * the AI Connector owns both the credentials and the network call.
 *
 * The summary is stored as product meta only when an admin explicitly triggers
 * generation (see AiProductSummaryController). Nothing is generated on page load,
 * and when no provider is configured the manual / deterministic AI Feed path is
 * completely unaffected.
 */
final class AiProductSummaryService
{
    public const MODULE = 'ai_bridge';

    public const META_KEY = '_polski_ai_summary';

    /**
     * Hard cap on the stored summary length (characters) to keep front-matter compact.
     */
    private const MAX_LENGTH = 600;

    /**
     * Whether the feature is usable right now: module enabled AND a text-capable
     * AI provider is configured. No network call is performed.
     */
    public function isAvailable(): bool
    {
        if (! \Polski\Admin\ModulesPage::isModuleEnabled(self::MODULE)) {
            return false;
        }

        return AiClient::isAvailableForText();
    }

    /**
     * Generate a factual summary for the product.
     *
     * Returns the summary string on success, or null when the feature is
     * unavailable or the provider could not produce a usable answer. This method
     * does NOT persist anything; callers decide whether to store the result.
     */
    public function generate(WC_Product $product): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $source = $this->buildSourceText($product);
        if ($source === '') {
            return null;
        }

        $instruction = implode(' ', [
            'You write concise, factual product summaries for an online shop.',
            'Use ONLY the facts provided in the user message; do not invent specifications, prices, or claims.',
            'Write 1-3 short sentences in the same language as the product details.',
            'No marketing superlatives, no legal or compliance claims, no headings, no markdown.',
            'Return JSON matching the schema.',
        ]);

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary'],
            'properties' => [
                'summary' => [
                    'type' => 'string',
                    'description' => 'A short factual product summary, plain text.',
                ],
            ],
        ];

        $decoded = AiClient::classifyJson($instruction, $source, $schema);
        if ($decoded === null || ! isset($decoded['summary']) || ! is_string($decoded['summary'])) {
            return null;
        }

        $summary = $this->normalize($decoded['summary']);

        return $summary !== '' ? $summary : null;
    }

    /**
     * Generate and persist the summary as product meta. Returns the stored
     * summary, or null if generation failed (in which case nothing is written).
     */
    public function generateAndStore(WC_Product $product): ?string
    {
        $summary = $this->generate($product);
        if ($summary === null) {
            return null;
        }

        $product->update_meta_data(self::META_KEY, $summary);
        $product->save();

        return $summary;
    }

    /**
     * Read the stored summary for a product, or empty string when none is saved.
     */
    public function getStored(WC_Product $product): string
    {
        $value = $product->get_meta(self::META_KEY);

        return is_string($value) ? $this->normalize($value) : '';
    }

    /**
     * Assemble the deterministic, factual source text the model summarises.
     *
     * Reuses the exact fact set the AI Feed already exposes via its public
     * filter, plus the product's own short / long description, so the model only
     * ever sees data the shop already publishes.
     */
    private function buildSourceText(WC_Product $product): string
    {
        $lines = [];

        $name = trim(wp_strip_all_tags($product->get_name()));
        if ($name !== '') {
            $lines[] = 'Product: ' . $name;
        }

        $shortDescription = trim(wp_strip_all_tags((string) $product->get_short_description()));
        if ($shortDescription !== '') {
            $lines[] = 'Short description: ' . $shortDescription;
        }

        $description = trim(wp_strip_all_tags((string) $product->get_description()));
        if ($description !== '') {
            $lines[] = 'Description: ' . $this->clip($description, 1500);
        }

        /**
         * Reuse the AI Feed fact list (label/value pairs). ProductMarkdownBuilder
         * seeds the same hook, so this matches what the shop already surfaces.
         *
         * @var mixed $rows
         */
        $rows = apply_filters('polski/ai_feed/product_facts', [], $product);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (! is_array($row) || ! isset($row[0], $row[1])) {
                    continue;
                }
                $label = trim(wp_strip_all_tags((string) $row[0]));
                $value = trim(wp_strip_all_tags((string) $row[1]));
                if ($label !== '' && $value !== '') {
                    $lines[] = $label . ': ' . $value;
                }
            }
        }

        return trim(implode("\n", $lines));
    }

    private function normalize(string $value): string
    {
        $value = trim(wp_strip_all_tags($value));
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return $this->clip($value, self::MAX_LENGTH);
    }

    private function clip(string $value, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }
}
