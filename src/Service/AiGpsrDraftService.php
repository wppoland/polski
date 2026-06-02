<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\AI\AiClient;
use WC_Product;

/**
 * AI-assisted, draft-only GPSR safety-text generator.
 *
 * Produces a DRAFT of product safety warnings and usage instructions for a
 * WooCommerce product by delegating to the WordPress 7.0+ AI Client through the
 * Polski\AI\AiClient facade. The plugin never stores a provider key and never
 * makes an outbound HTTP request itself - the AI Connector owns both the
 * credentials and the network call.
 *
 * IMPORTANT: this service NEVER writes the real, merchant-entered GPSR fields
 * managed by GPSRService (_polski_gpsr_safety_warnings, _polski_gpsr_instructions,
 * etc.). Those carry legal weight and must remain human-authored. The generated
 * text is stored ONLY in a separate, clearly-named draft meta key
 * (self::DRAFT_META_KEY) as a starting point for human review. An admin must read
 * the draft and copy it across manually after verification.
 */
final class AiGpsrDraftService
{
    public const MODULE = 'ai_bridge';

    /**
     * Separate draft-only meta. NOT one of the real GPSR field keys.
     *
     * @var string
     */
    public const DRAFT_META_KEY = '_polski_ai_gpsr_draft';

    /**
     * Hard cap on each generated draft field (characters).
     */
    private const MAX_LENGTH = 1500;

    public function __construct(
        private readonly GPSRService $gpsr,
    ) {
    }

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
     * Generate a DRAFT of GPSR safety text for the product.
     *
     * Returns a small associative array with 'safety_warnings', 'instructions'
     * and a 'note' on success, or null when the feature is unavailable or the
     * provider could not produce a usable answer. This method does NOT persist
     * anything; callers decide whether to store the draft.
     *
     * @return array{safety_warnings: string, instructions: string, note: string, generated_at: int}|null
     */
    public function generateDraft(WC_Product $product): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $source = $this->buildSourceText($product);
        if ($source === '') {
            return null;
        }

        $instruction = implode(' ', [
            'You draft product safety warnings and usage instructions for an online shop.',
            'Use ONLY the facts provided in the user message; do not invent hazards, certifications, specifications, or claims.',
            'Write plain text in the same language as the product details, suitable for a human editor to review and refine.',
            'Do NOT state or imply legal or regulatory compliance; produce a starting draft only.',
            'No marketing language, no headings, no markdown.',
            'Return JSON matching the schema. Leave a field as an empty string when the provided facts do not support it.',
        ]);

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['safety_warnings', 'instructions'],
            'properties' => [
                'safety_warnings' => [
                    'type' => 'string',
                    'description' => 'Draft safety warnings for the product, plain text.',
                ],
                'instructions' => [
                    'type' => 'string',
                    'description' => 'Draft usage instructions for the product, plain text.',
                ],
            ],
        ];

        $decoded = AiClient::classifyJson($instruction, $source, $schema);
        if ($decoded === null) {
            return null;
        }

        $safetyWarnings = isset($decoded['safety_warnings']) && is_string($decoded['safety_warnings'])
            ? $this->normalize($decoded['safety_warnings'])
            : '';
        $instructions = isset($decoded['instructions']) && is_string($decoded['instructions'])
            ? $this->normalize($decoded['instructions'])
            : '';

        if ($safetyWarnings === '' && $instructions === '') {
            return null;
        }

        return [
            'safety_warnings' => $safetyWarnings,
            'instructions' => $instructions,
            'note' => __('AI-generated draft for review only. Verify all details before publishing; this is not a guarantee of compliance.', 'polski'),
            'generated_at' => time(),
        ];
    }

    /**
     * Generate a draft and persist it to the separate draft meta only. Returns
     * the stored draft, or null if generation failed (nothing is written then).
     *
     * This writes ONLY self::DRAFT_META_KEY. It never touches the real GPSR
     * fields read by GPSRService::getGPSRData().
     *
     * @return array{safety_warnings: string, instructions: string, note: string, generated_at: int}|null
     */
    public function generateAndStoreDraft(WC_Product $product): ?array
    {
        $draft = $this->generateDraft($product);
        if ($draft === null) {
            return null;
        }

        $product->update_meta_data(self::DRAFT_META_KEY, $draft);
        $product->save();

        return $draft;
    }

    /**
     * Read the stored draft for a product, or null when none is saved.
     *
     * @return array{safety_warnings: string, instructions: string, note: string, generated_at: int}|null
     */
    public function getStoredDraft(WC_Product $product): ?array
    {
        $value = $product->get_meta(self::DRAFT_META_KEY);
        if (! is_array($value)) {
            return null;
        }

        return [
            'safety_warnings' => isset($value['safety_warnings']) && is_string($value['safety_warnings'])
                ? $this->normalize($value['safety_warnings'])
                : '',
            'instructions' => isset($value['instructions']) && is_string($value['instructions'])
                ? $this->normalize($value['instructions'])
                : '',
            'note' => isset($value['note']) && is_string($value['note'])
                ? $value['note']
                : '',
            'generated_at' => isset($value['generated_at']) ? (int) $value['generated_at'] : 0,
        ];
    }

    /**
     * Assemble the factual source text the model grounds its draft in.
     *
     * Uses the product's own public description plus the already-captured GPSR
     * fields, so the model only ever sees data the shop already holds.
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

        // Ground in the merchant-entered GPSR fields (read-only) so the draft
        // builds on existing safety data rather than contradicting it.
        $gpsrData = $this->gpsr->getGPSRData($product);
        $labels = [
            'manufacturer_name' => 'Manufacturer',
            'manufacturer_address' => 'Manufacturer address',
            'importer_name' => 'Importer',
            'importer_address' => 'Importer address',
            'responsible_person' => 'EU responsible person',
            'product_identifier' => 'Product identifier',
            'safety_warnings' => 'Existing safety warnings',
            'instructions' => 'Existing instructions',
        ];
        foreach ($labels as $key => $label) {
            $value = isset($gpsrData[$key]) ? trim(wp_strip_all_tags($gpsrData[$key])) : '';
            if ($value !== '') {
                $lines[] = $label . ': ' . $value;
            }
        }

        return trim(implode("\n", $lines));
    }

    private function normalize(string $value): string
    {
        $value = trim(wp_strip_all_tags($value));
        $value = (string) preg_replace('/[ \t]+/u', ' ', $value);
        $value = (string) preg_replace('/\n{3,}/', "\n\n", $value);

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
