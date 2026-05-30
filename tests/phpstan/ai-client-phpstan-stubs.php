<?php

declare(strict_types=1);

/**
 * PHPStan-only stubs for the WordPress 7.0+ AI Client API
 * (not Composer-installed; provided by WordPress core / a provider plugin).
 * Loaded via .phpstan.neon bootstrapFiles — never autoloaded in WordPress.
 *
 * Minimal API surface used by src/AI/AiClient.php. AiClient wraps every call in
 * try/catch (\Throwable), so any provider that deviates from this surface
 * degrades gracefully to "no AI augmentation" at runtime.
 *
 * @see https://make.wordpress.org/core/ (AI Client / WP_AI_Client_Prompt_Builder)
 */

namespace {
    /**
     * Fluent prompt builder returned by wp_ai_client_prompt().
     */
    class WP_AI_Client_Prompt_Builder
    {
        public function is_supported_for_text_generation(): bool
        {
            return false;
        }

        public function using_system_instruction(string $instruction): self
        {
            return $this;
        }

        /**
         * @param array<string, mixed> $schema
         */
        public function as_json_response(array $schema): self
        {
            return $this;
        }

        public function using_temperature(float $temperature): self
        {
            return $this;
        }

        public function using_model_preference(string ...$models): self
        {
            return $this;
        }

        /**
         * The real API returns a string; typed as mixed so AiClient's defensive
         * WP_Error / non-string guards remain meaningful to static analysis.
         *
         * @return mixed
         */
        public function generate_text()
        {
            return '';
        }
    }

    function wp_ai_client_prompt(string $prompt = ''): WP_AI_Client_Prompt_Builder
    {
        return new WP_AI_Client_Prompt_Builder();
    }
}
