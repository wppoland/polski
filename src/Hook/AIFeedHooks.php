<?php

declare(strict_types=1);

namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\AIFeed\PostMarkdownBuilder;
use Polski\AIFeed\ProductMarkdownBuilder;
use Polski\AIFeed\RequestNegotiator;
use Polski\Contract\HasHooks;
use WP_Post;

/**
 * Serves Markdown representations of singular content for AI agents.
 *
 * Trigger via Accept: text/markdown header or ?output_format=md query arg.
 */
final class AIFeedHooks implements HasHooks
{
    private const OPTION = 'polski_ai_feed';

    private const DEFAULT_POST_TYPES = ['post', 'page', 'product'];

    public function __construct(
        private readonly RequestNegotiator $negotiator,
        private readonly PostMarkdownBuilder $postBuilder,
        private readonly ProductMarkdownBuilder $productBuilder,
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('query_vars', [$this, 'registerQueryVar']);
        add_action('template_redirect', [$this, 'maybeServeMarkdown'], 0);
        add_action('wp_head', [$this, 'printAlternateLink'], 1);
        add_filter('post_row_actions', [$this, 'addRowAction'], 10, 2);
        add_filter('page_row_actions', [$this, 'addRowAction'], 10, 2);
    }

    /**
     * @param string[] $vars
     * @return string[]
     */
    public function registerQueryVar(array $vars): array
    {
        $vars[] = 'output_format';

        return $vars;
    }

    public function maybeServeMarkdown(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (is_feed() || is_embed() || is_trackback()) {
            return;
        }

        if (! is_singular($this->postTypes())) {
            return;
        }

        $post = get_queried_object();
        if (! $post instanceof WP_Post) {
            return;
        }

        if ($post->post_status !== 'publish' && ! current_user_can('read_post', $post->ID)) {
            return;
        }

        if (! $this->negotiator->wantsMarkdown()) {
            return;
        }

        $this->sendHeaders();

        if (post_password_required($post)) {
            if ($this->negotiator->isHeadRequest()) {
                exit;
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown body for agents.
            echo $this->postBuilder->buildPasswordRequired($post);
            exit;
        }

        if ($this->negotiator->isHeadRequest()) {
            exit;
        }

        $markdown = $post->post_type === 'product'
            ? $this->productBuilder->build($post)
            : $this->postBuilder->build($post);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown body for agents.
        echo $markdown;
        exit;
    }

    public function printAlternateLink(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (! is_singular($this->postTypes())) {
            return;
        }

        $url = add_query_arg('output_format', 'md', (string) (get_permalink() ?: ''));

        echo '<link rel="alternate" type="text/markdown" href="' . esc_url($url) . '" />' . "\n";
    }

    /**
     * @param string[] $actions
     * @return string[]
     */
    public function addRowAction(array $actions, mixed $post): array
    {
        if (! $this->isEnabled()) {
            return $actions;
        }

        if (! $post instanceof WP_Post) {
            return $actions;
        }

        if (! in_array($post->post_type, $this->postTypes(), true)) {
            return $actions;
        }

        $url = $this->buildPreviewUrl($post);
        if ($url === null) {
            return $actions;
        }

        $link = sprintf(
            '<a href="%s" rel="bookmark">%s</a>',
            esc_url($url),
            esc_html__('View AI Version', 'polski'),
        );

        if (isset($actions['polski_ai_feed'])) {
            return $actions;
        }

        if (! isset($actions['view'])) {
            $actions['polski_ai_feed'] = $link;

            return $actions;
        }

        $output = [];
        foreach ($actions as $key => $html) {
            $output[$key] = $html;
            if ($key === 'view') {
                $output['polski_ai_feed'] = $link;
            }
        }

        return $output;
    }

    private function buildPreviewUrl(WP_Post $post): ?string
    {
        $postTypeObject = get_post_type_object($post->post_type);
        if ($postTypeObject === null || ! is_post_type_viewable($postTypeObject)) {
            return null;
        }

        $base = null;

        if (in_array($post->post_status, ['pending', 'draft', 'future'], true)) {
            if (! current_user_can('edit_post', $post->ID)) {
                return null;
            }
            $base = get_preview_post_link($post);
        } elseif ($post->post_status !== 'trash') {
            $base = get_permalink($post);
        }

        if (! is_string($base) || $base === '') {
            return null;
        }

        return add_query_arg('output_format', 'md', $base);
    }

    private function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('Content-Type: text/markdown; charset=UTF-8');
        header('Vary: Accept');
    }

    /**
     * @return string[]
     */
    private function postTypes(): array
    {
        $settings = get_option(self::OPTION, []);
        $configured = is_array($settings) && isset($settings['post_types']) && is_array($settings['post_types'])
            ? array_values(array_filter(array_map('strval', $settings['post_types'])))
            : self::DEFAULT_POST_TYPES;

        /**
         * Filter post types that should be served as Markdown for AI agents.
         *
         * @param string[] $postTypes Post type slugs.
         */
        $filtered = (array) apply_filters('polski/ai_feed/post_types', $configured);

        return array_values(array_filter(array_map('strval', $filtered)));
    }

    private function isEnabled(): bool
    {
        $settings = get_option(self::OPTION, []);
        $enabled = is_array($settings) && array_key_exists('enabled', $settings)
            ? (bool) $settings['enabled']
            : true;

        /**
         * Filter the master enable switch for the AI Feed module.
         *
         * @param bool $enabled Whether AI Feed is enabled.
         */
        return (bool) apply_filters('polski/ai_feed/enabled', $enabled);
    }
}
