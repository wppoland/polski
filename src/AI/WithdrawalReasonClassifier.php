<?php

declare(strict_types=1);
namespace Polski\AI;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Repository\WithdrawalRepository;

/**
 * Optional AI augmentation: when a customer or operator files a withdrawal
 * with a free-text reason, this service asks the WordPress AI Client to map
 * that reason to one of a fixed set of e-commerce return categories.
 *
 * The classification is persisted on the withdrawal row (ai_category +
 * ai_confidence columns added by Migration_2_3_0) so the operator dashboard
 * can group / triage declarations without re-reading every reason.
 *
 * The service is *strictly opt-in* and *strictly degrading*:
 *   - `polski_ai_features_enabled` option must be 'yes'
 *   - WordPress AI Client (`wp_ai_client_prompt()`) must be present, with at
 *     least one provider configured for text generation
 *   - any failure is silently absorbed; the withdrawal row stays NULL on
 *     ai_category and the rest of the flow is untouched
 *
 * The plugin never makes outbound HTTP calls itself; the provider plugin
 * (Vercel AI Gateway, Anthropic, Google, OpenAI, ...) owns the network call.
 */
final class WithdrawalReasonClassifier implements HasHooks
{
    /**
     * Canonical set of return categories. Kept short so providers do not have
     * to invent synonyms and so operator dashboards can show meaningful filters.
     */
    private const CATEGORIES = [
        'defective',
        'wrong_item',
        'size_mismatch',
        'changed_mind',
        'late_delivery',
        'damaged_in_transit',
        'not_as_described',
        'other',
    ];

    private const MIN_REASON_LENGTH = 12;

    public function __construct(
        private readonly WithdrawalRepository $repository,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('polski/withdrawal/requested', [$this, 'classify'], 50, 1);
        add_action('polski/withdrawal/guest_requested', [$this, 'classify'], 50, 1);
        add_action('polski/withdrawal/manual_registered', [$this, 'classify'], 50, 1);
    }

    public function classify(int $withdrawalId): void
    {
        if (! $this->enabled()) {
            return;
        }

        if (! AiClient::isAvailableForText()) {
            return;
        }

        $withdrawal = $this->repository->findById($withdrawalId);
        if ($withdrawal === null) {
            return;
        }

        $reason = trim((string) $withdrawal->reason);
        if (strlen($reason) < self::MIN_REASON_LENGTH) {
            return;
        }

        $decoded = AiClient::classifyJson(
            instruction: $this->instruction(),
            prompt: $reason,
            schema: $this->schema(),
            modelHints: $this->modelHints(),
            temperature: 0.1,
        );

        if ($decoded === null) {
            return;
        }

        $category = isset($decoded['category']) ? (string) $decoded['category'] : '';
        if ($category === '' || ! in_array($category, self::CATEGORIES, true)) {
            return;
        }

        $confidence = isset($decoded['confidence']) && is_numeric($decoded['confidence'])
            ? max(0.0, min(1.0, (float) $decoded['confidence']))
            : null;

        $this->repository->setAiClassification($withdrawalId, $category, $confidence);

        /**
         * Fires after the AI Client has classified a withdrawal reason.
         *
         * @param int         $withdrawalId
         * @param string      $category   One of self::CATEGORIES.
         * @param float|null  $confidence 0..1 or null.
         */
        do_action('polski/withdrawal/ai_classified', $withdrawalId, $category, $confidence);
    }

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    private function enabled(): bool
    {
        return get_option('polski_ai_features_enabled', 'no') === 'yes';
    }

    private function instruction(): string
    {
        $categoryList = implode(', ', self::CATEGORIES);

        return sprintf(
            'You are a return-reason classifier for an e-commerce store. '
            . 'Read the customer-supplied reason and classify it into exactly one of: %s. '
            . 'Respond with JSON matching the supplied schema, no prose. '
            . 'Use "other" if nothing fits. '
            . 'Set confidence to your subjective certainty (0 = guess, 1 = certain).',
            $categoryList,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'category' => [
                    'type' => 'string',
                    'enum' => self::CATEGORIES,
                ],
                'confidence' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],
            ],
            'required' => ['category'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return list<string>
     */
    private function modelHints(): array
    {
        /**
         * Allow integrators to influence which models the AI Client routes to.
         * Returning an empty list (default) lets the AI Client pick.
         *
         * @param list<string> $preferences
         */
        $hints = apply_filters('polski/ai/withdrawal_classifier_model_preference', []);

        $hints = is_array($hints) ? $hints : [];

        return array_values(array_filter(array_map('strval', $hints), static fn ($v) => $v !== ''));
    }
}
