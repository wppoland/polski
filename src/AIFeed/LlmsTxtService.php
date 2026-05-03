<?php

declare(strict_types=1);

namespace Polski\AIFeed;

defined('ABSPATH') || exit;

use Polski\Enum\LegalPageType;

/**
 * Builds the /llms.txt manifest defined by https://llmstxt.org so AI agents
 * can discover the site's machine-readable surface from a single well-known
 * URL.
 *
 * The document is plain Markdown:
 *   # Site name
 *   > Site description
 *   ## Section
 *   - [Title](url): description
 *
 * Sections are extensible via filters so polski-pro and integrators can add
 * their own without touching this class.
 */
final class LlmsTxtService
{

    public function build(): string
    {
        $title = trim(wp_strip_all_tags((string) get_bloginfo('name'))) ?: 'WordPress';
        $description = trim(wp_strip_all_tags((string) get_bloginfo('description')));

        $lines = ['# ' . $title, ''];

        if ($description !== '') {
            $lines[] = '> ' . $description;
            $lines[] = '';
        }

        $sections = $this->defaultSections();

        /**
         * Filter the sections shown in /llms.txt before rendering.
         *
         * @param array<string, array<int, array{0: string, 1: string, 2?: string}>> $sections
         *        Map of section heading => list of [title, url, optional description] tuples.
         */
        $sections = (array) apply_filters('polski/ai_feed/llms_txt_sections', $sections);

        foreach ($sections as $heading => $items) {
            if (! is_array($items) || $items === []) {
                continue;
            }

            $heading = is_string($heading) ? trim($heading) : '';
            if ($heading === '') {
                continue;
            }

            $lines[] = '## ' . $heading;
            $lines[] = '';

            foreach ($items as $item) {
                if (! is_array($item) || ! isset($item[0], $item[1])) {
                    continue;
                }
                $title = (string) $item[0];
                $url = (string) $item[1];
                if ($title === '' || $url === '') {
                    continue;
                }
                $line = '- [' . $title . '](' . $url . ')';
                if (isset($item[2]) && is_string($item[2]) && $item[2] !== '') {
                    $line .= ': ' . $item[2];
                }
                $lines[] = $line;
            }

            $lines[] = '';
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }

    /**
     * @return array<string, array<int, array{0: string, 1: string, 2?: string}>>
     */
    private function defaultSections(): array
    {
        $sections = [];

        $legal = $this->legalPagesItems();
        if ($legal !== []) {
            $sections[__('Legal', 'polski')] = $legal;
        }

        $shop = $this->shopItems();
        if ($shop !== []) {
            $sections[__('Shop', 'polski')] = $shop;
        }

        $categories = $this->productCategoriesItems();
        if ($categories !== []) {
            $sections[__('Product categories', 'polski')] = $categories;
        }

        return $sections;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2?: string}>
     */
    private function legalPagesItems(): array
    {
        $items = [];

        foreach (LegalPageType::cases() as $type) {
            $pageId = (int) get_option($type->optionKey(), 0);
            if ($pageId <= 0) {
                continue;
            }

            $url = (string) get_permalink($pageId);
            $title = trim(wp_strip_all_tags((string) get_the_title($pageId)));
            if ($url === '' || $title === '') {
                continue;
            }

            $items[] = [
                $title,
                $this->withMarkdown($url),
                $this->legalPageDescription($type),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2?: string}>
     */
    private function shopItems(): array
    {
        if (! function_exists('wc_get_page_id')) {
            return [];
        }

        $shopPageId = (int) wc_get_page_id('shop');
        if ($shopPageId <= 0) {
            return [];
        }

        $url = (string) get_permalink($shopPageId);
        $title = trim(wp_strip_all_tags((string) get_the_title($shopPageId)));

        if ($url === '' || $title === '') {
            return [];
        }

        return [
            [$title, $url, __('Storefront', 'polski')],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string, 2?: string}>
     */
    private function productCategoriesItems(): array
    {
        if (! taxonomy_exists('product_cat')) {
            return [];
        }

        $limit = (int) apply_filters('polski/ai_feed/llms_txt_category_limit', 20);
        if ($limit <= 0) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'number' => $limit,
            'orderby' => 'count',
            'order' => 'DESC',
        ]);

        if (! is_array($terms)) {
            return [];
        }

        $items = [];
        foreach ($terms as $term) {
            if (! $term instanceof \WP_Term) {
                continue;
            }
            $url = get_term_link($term);
            if (! is_string($url) || $url === '' || ! str_starts_with($url, 'http')) {
                continue;
            }
            $items[] = [$term->name, $url];
        }

        return $items;
    }

    private function withMarkdown(string $url): string
    {
        if ($url === '') {
            return '';
        }

        return add_query_arg('output_format', 'md', $url);
    }

    private function legalPageDescription(LegalPageType $type): string
    {
        return match ($type) {
            LegalPageType::Terms => __('Terms and conditions', 'polski'),
            LegalPageType::Privacy => __('Privacy policy', 'polski'),
            LegalPageType::Returns => __('Returns and withdrawal policy', 'polski'),
            LegalPageType::Complaints => __('Complaints procedure', 'polski'),
        };
    }
}
