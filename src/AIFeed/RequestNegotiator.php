<?php

declare(strict_types=1);

namespace Polski\AIFeed;

defined('ABSPATH') || exit;

/**
 * Detects whether the current request asks for a Markdown representation.
 *
 * Triggers:
 *  - Query var output_format=md
 *  - Accept header listing text/markdown (excluding explicit q=0)
 */
final class RequestNegotiator
{
    public function wantsMarkdown(): bool
    {
        $format = get_query_var('output_format');

        if (is_string($format) && sanitize_key($format) === 'md') {
            return true;
        }

        if (! isset($_SERVER['HTTP_ACCEPT'])) {
            return false;
        }

        $accept = strtolower(sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_ACCEPT'])));

        if ($accept === '' || ! str_contains($accept, 'text/markdown')) {
            return false;
        }

        if (preg_match('/text\/markdown\s*;\s*q=\s*0(?:\.0)?\b/', $accept)) {
            return false;
        }

        return true;
    }

    public function isHeadRequest(): bool
    {
        if (! isset($_SERVER['REQUEST_METHOD'])) {
            return false;
        }

        return strtoupper(sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD']))) === 'HEAD';
    }
}
