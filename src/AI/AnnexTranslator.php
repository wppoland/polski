<?php

declare(strict_types=1);
namespace Polski\AI;

defined('ABSPATH') || exit;

/**
 * Translate the canonical Polish Annex I(B) form into customer locales that the
 * static AnnexMultiLanguageService does not cover natively. Result is cached in
 * a transient keyed by content hash + locale so the AI Client is queried at
 * most once per (annex revision, target locale) pair.
 *
 * Like the rest of the AI integration the service is strictly opt-in
 * (`polski_ai_features_enabled` option) and strictly degrading - if the AI
 * Client is unavailable, the call returns null and the caller falls back to
 * the source Polish text.
 */
final class AnnexTranslator
{
    /** Transient TTL: 1 day. AI-translated annex pages change rarely. */
    private const CACHE_TTL = DAY_IN_SECONDS;

    private const CACHE_PREFIX = 'polski_annex_xlate_';

    /**
     * @return string|null Translated HTML (wp_kses_post sanitised) or null on failure.
     */
    public function translate(string $sourceHtml, string $targetLocale): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $targetLocale = $this->normaliseLocale($targetLocale);

        if ($targetLocale === '' || $targetLocale === 'pl') {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . hash('sha256', $sourceHtml . '|' . $targetLocale);
        $cached = get_transient($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if (! AiClient::isAvailableForText()) {
            return null;
        }

        $instruction = sprintf(
            'You are translating a legally significant withdrawal form (Annex I(B) of EU Directive 2011/83/EU) '
            . 'from Polish into the user-supplied target language. '
            . 'Preserve every HTML tag, every attribute, every order item and every numeric placeholder verbatim. '
            . 'Translate only the visible text content. '
            . 'Do not add commentary. Target language code: %s.',
            $targetLocale,
        );

        $decoded = AiClient::classifyJson(
            instruction: $instruction,
            prompt: $sourceHtml,
            schema: [
                'type' => 'object',
                'properties' => [
                    'html' => ['type' => 'string'],
                ],
                'required' => ['html'],
                'additionalProperties' => false,
            ],
            temperature: 0.0,
        );

        if ($decoded === null || ! isset($decoded['html']) || ! is_string($decoded['html'])) {
            return null;
        }

        $clean = wp_kses_post($decoded['html']);

        if ($clean === '' || $clean === $sourceHtml) {
            return null;
        }

        set_transient($cacheKey, $clean, self::CACHE_TTL);

        return $clean;
    }

    /**
     * Drop the translation cache for a single locale or - when called with no
     * argument - for every locale. Use after the operator edits the source
     * annex page so customers get the fresh wording on next access.
     */
    public function forget(?string $sourceHtml = null, ?string $targetLocale = null): void
    {
        if ($sourceHtml !== null && $targetLocale !== null) {
            delete_transient(self::CACHE_PREFIX . hash('sha256', $sourceHtml . '|' . $this->normaliseLocale($targetLocale)));
        }
    }

    private function enabled(): bool
    {
        return get_option('polski_ai_features_enabled', 'no') === 'yes';
    }

    private function normaliseLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        // pl_PL -> pl, de_DE -> de etc.
        if (preg_match('/^([a-z]{2,3})(?:[_-]|$)/', $locale, $m) === 1) {
            return $m[1];
        }

        return preg_match('/^[a-z]{2,3}$/', $locale) === 1 ? $locale : '';
    }
}
