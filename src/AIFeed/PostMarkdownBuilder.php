<?php

declare(strict_types=1);

namespace Polski\AIFeed;

defined('ABSPATH') || exit;

use WP_Post;

/**
 * Builds a Markdown document for a singular post or page.
 */
final class PostMarkdownBuilder
{
    public function __construct(private readonly MarkdownConverter $converter)
    {
    }

    public function build(WP_Post $postObject): string
    {
        $title = $this->plainTitle($postObject);
        $permalink = (string) (get_permalink($postObject) ?: '');

        $bodyMarkdown = $this->converter->htmlToMarkdown($this->renderContent($postObject));

        $frontMatter = $this->converter->frontMatter([
            'title' => $title,
            'permalink' => $permalink,
            'modified' => (string) (get_post_modified_time('c', true, $postObject) ?: ''),
        ]);

        $sections = [];

        if ($frontMatter !== '') {
            $sections[] = $frontMatter;
        }

        $sections[] = '# ' . $title;
        $sections[] = trim($bodyMarkdown);

        $document = implode("\n\n", array_filter($sections, static fn (string $part): bool => $part !== '')) . "\n";

        /**
         * Filter the Markdown document for a post.
         *
         * @param string  $document  Final Markdown.
         * @param WP_Post $postObject Post being rendered.
         */
        return (string) apply_filters('polski/ai_feed/post_markdown', $document, $postObject);
    }

    public function buildPasswordRequired(WP_Post $postObject): string
    {
        $title = $this->plainTitle($postObject);
        $message = __('This content is password protected.', 'polski');

        $document = "# {$title}\n\n{$message}\n";

        /**
         * Filter the Markdown shown when a post is password protected.
         *
         * @param string  $document  Markdown body.
         * @param WP_Post $postObject Post being rendered.
         */
        return (string) apply_filters('polski/ai_feed/password_required', $document, $postObject);
    }

    private function renderContent(WP_Post $postObject): string
    {
        global $post;

        $backup = $post;
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- required for the_content filter.
        $post = $postObject;
        setup_postdata($post);

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
        $html = (string) apply_filters('the_content', $postObject->post_content);

        wp_reset_postdata();
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        $post = $backup;

        return $html;
    }

    private function plainTitle(WP_Post $postObject): string
    {
        $title = trim(wp_strip_all_tags(get_the_title($postObject)));

        return $title === '' ? __('(Untitled)', 'polski') : $title;
    }
}
