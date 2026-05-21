<?php

declare(strict_types=1);
namespace Polski\AI;

defined('ABSPATH') || exit;

/**
 * Thin adapter on top of the WordPress 7.0+ AI Client API
 * (`wp_ai_client_prompt()` -> `WP_AI_Client_Prompt_Builder`).
 *
 * The adapter is intentionally tolerant:
 *   - if AI Client is not available (older WordPress or no provider configured),
 *     every method short-circuits to a "not supported" answer;
 *   - if the call throws or returns WP_Error, the helper returns null instead
 *     of bubbling failures to the consumer service.
 *
 * Consumers are therefore free to use these helpers without `function_exists()`
 * checks or try/catch noise; failure simply means "no AI augmentation this
 * time" and the plugin continues to function on its deterministic path.
 *
 * The plugin itself never makes outbound HTTP requests - the underlying
 * provider plugin (Vercel AI Gateway, Anthropic, Google, OpenAI, ...) owns
 * both the network call and the credentials.
 */
final class AiClient
{
    /**
     * Returns true when WordPress AI Client is loaded AND at least one
     * provider is configured for text generation. No API call is performed.
     */
    public static function isAvailableForText(): bool
    {
        if (! function_exists('wp_ai_client_prompt')) {
            return false;
        }

        try {
            $builder = wp_ai_client_prompt();
        } catch (\Throwable $e) {
            return false;
        }

        if (! is_object($builder) || ! method_exists($builder, 'is_supported_for_text_generation')) {
            return false;
        }

        try {
            return (bool) $builder->is_supported_for_text_generation();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Send a one-shot prompt that expects a JSON answer conforming to the
     * supplied JSON schema. Returns the decoded associative array on success
     * or null on any failure (no provider, prevented prompt, WP_Error, malformed JSON).
     *
     * @param string                                       $instruction Short system instruction describing the task.
     * @param string                                       $prompt      The user text to classify / summarise.
     * @param array<string, mixed>                         $schema      A JSON schema describing the expected response.
     * @param list<string>                                 $modelHints  Optional non-binding model preferences.
     * @param float                                        $temperature 0.0 - 1.0; lower = more deterministic.
     * @return array<string, mixed>|null
     */
    public static function classifyJson(
        string $instruction,
        string $prompt,
        array $schema,
        array $modelHints = [],
        float $temperature = 0.1
    ): ?array {
        if (! self::isAvailableForText()) {
            return null;
        }

        try {
            $builder = wp_ai_client_prompt($prompt);

            if (method_exists($builder, 'using_system_instruction')) {
                $builder = $builder->using_system_instruction($instruction);
            }

            if (method_exists($builder, 'as_json_response')) {
                $builder = $builder->as_json_response($schema);
            }

            if (method_exists($builder, 'using_temperature')) {
                $builder = $builder->using_temperature(max(0.0, min(1.0, $temperature)));
            }

            if ($modelHints !== [] && method_exists($builder, 'using_model_preference')) {
                $builder = $builder->using_model_preference(...$modelHints);
            }

            $text = $builder->generate_text();
        } catch (\Throwable $e) {
            return null;
        }

        if (is_wp_error($text) || ! is_string($text) || $text === '') {
            return null;
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }
}
