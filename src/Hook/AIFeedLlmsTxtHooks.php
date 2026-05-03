<?php

declare(strict_types=1);

namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\AIFeed\LlmsTxtService;
use Polski\Contract\HasHooks;

/**
 * Serves /llms.txt at the site root for AI agent discovery.
 *
 * Implements the open standard at https://llmstxt.org. Agents look for the
 * file at a fixed root URL, so we intercept the request very early on `init`
 * before WordPress reaches its 404 path.
 */
final class AIFeedLlmsTxtHooks implements HasHooks
{
    public function __construct(private readonly LlmsTxtService $service)
    {
    }

    public function registerHooks(): void
    {
        add_action('init', [$this, 'maybeServe'], 0);
    }

    public function maybeServe(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (! isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $requestUri = sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI']));
        $path = (string) wp_parse_url($requestUri, PHP_URL_PATH);

        if ($path !== '/llms.txt') {
            return;
        }

        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper(sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD'])))
            : 'GET';

        if ($method !== 'GET' && $method !== 'HEAD') {
            return;
        }

        $body = $this->service->build();

        if (! headers_sent()) {
            status_header(200);
            header('Content-Type: text/markdown; charset=UTF-8');
            header('Cache-Control: public, max-age=3600');
            header('X-Robots-Tag: index, follow');
        }

        if ($method === 'HEAD') {
            exit;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown body for agents.
        echo $body;
        exit;
    }

    private function isEnabled(): bool
    {
        $settings = get_option('polski_ai_feed', []);
        $enabled = is_array($settings) && array_key_exists('llms_txt_enabled', $settings)
            ? (bool) $settings['llms_txt_enabled']
            : true;

        /**
         * Master toggle for the /llms.txt endpoint.
         *
         * @param bool $enabled Whether /llms.txt is served.
         */
        return (bool) apply_filters('polski/ai_feed/llms_txt_enabled', $enabled);
    }
}
