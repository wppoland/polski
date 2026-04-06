<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * FAQ module with custom post type, categories, shortcode, and Schema.org FAQPage markup.
 *
 * Registers a FAQ CPT with hierarchical categories. Provides [polski_faq] shortcode
 * for displaying FAQs as an accordion. Outputs Schema.org FAQPage structured data.
 */
final class FaqService implements HasHooks
{
    private const CPT = 'polski_faq';
    private const TAXONOMY = 'faq_category';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('faq')) {
            return;
        }

        add_action('init', [$this, 'registerPostType']);
        add_action('init', [$this, 'registerTaxonomy']);
        add_shortcode('polski_faq', [$this, 'shortcodeCallback']);

        // Enqueue accordion styles/JS.
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerPostType(): void
    {
        register_post_type(self::CPT, [
            'labels' => [
                'name' => __('FAQ', 'polski'),
                'singular_name' => __('FAQ', 'polski'),
                'add_new' => __('Add question', 'polski'),
                'add_new_item' => __('Add FAQ question', 'polski'),
                'edit_item' => __('Edit FAQ', 'polski'),
                'search_items' => __('Search FAQ', 'polski'),
                'menu_name' => __('FAQ', 'polski'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'supports' => ['title', 'editor', 'page-attributes'],
            'menu_icon' => 'dashicons-editor-help',
            'capability_type' => 'page',
            'show_in_rest' => true,
        ]);
    }

    public function registerTaxonomy(): void
    {
        register_taxonomy(self::TAXONOMY, self::CPT, [
            'labels' => [
                'name' => __('FAQ Categories', 'polski'),
                'singular_name' => __('FAQ Category', 'polski'),
                'add_new_item' => __('Add FAQ category', 'polski'),
                'search_items' => __('Search FAQ categories', 'polski'),
            ],
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rewrite' => false,
        ]);
    }

    /**
     * [polski_faq] shortcode.
     *
     * Attributes:
     * - category: category ID or slug (optional)
     * - limit: number of FAQs to show (default: -1 = all)
     * - schema: output Schema.org FAQPage markup (default: yes)
     *
     * @param array<string, string>|string $atts
     */
    public function shortcodeCallback($atts): string
    {
        $atts = shortcode_atts([
            'category' => '',
            'limit' => -1,
            'schema' => 'yes',
        ], is_array($atts) ? $atts : [], 'polski_faq');

        $args = [
            'post_type' => self::CPT,
            'posts_per_page' => (int) $atts['limit'],
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ];

        // Category filter.
        if (! empty($atts['category'])) {
            $field = is_numeric($atts['category']) ? 'term_id' : 'slug';
            $args['tax_query'] = [[
                'taxonomy' => self::TAXONOMY,
                'field' => $field,
                'terms' => $atts['category'],
            ]];
        }

        $query = new \WP_Query($args);

        if (! $query->have_posts()) {
            return '';
        }

        $output = '<div class="polski-faq-accordion">';
        $schemaItems = [];

        while ($query->have_posts()) {
            $query->the_post();

            $question = get_the_title();
            $answer = apply_filters('the_content', get_the_content());
            $id = 'polski-faq-' . get_the_ID();

            $output .= '<div class="polski-faq-item" style="border:1px solid #e2e8f0;border-radius:8px;margin-bottom:8px;overflow:hidden">';
            $output .= sprintf(
                '<button class="polski-faq-question" onclick="this.classList.toggle(\'open\');this.nextElementSibling.style.display=this.classList.contains(\'open\')?\'block\':\'none\'" style="display:flex;align-items:center;justify-content:space-between;width:100%%;padding:14px 16px;background:#f8fafc;border:none;cursor:pointer;font-size:15px;font-weight:600;text-align:left" aria-expanded="false" aria-controls="%s"><span>%s</span><span class="polski-faq-icon" style="transition:transform .2s;font-size:18px">+</span></button>',
                esc_attr($id),
                esc_html($question),
            );
            $output .= sprintf(
                '<div id="%s" class="polski-faq-answer" style="display:none;padding:12px 16px;font-size:14px;line-height:1.6">%s</div>',
                esc_attr($id),
                wp_kses_post($answer),
            );
            $output .= '</div>';

            // Collect for Schema.org.
            if ($atts['schema'] === 'yes') {
                $schemaItems[] = [
                    '@type' => 'Question',
                    'name' => $question,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => wp_strip_all_tags($answer),
                    ],
                ];
            }
        }

        wp_reset_postdata();
        $output .= '</div>';

        // Schema.org FAQPage.
        if (! empty($schemaItems)) {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $schemaItems,
            ];

            $output .= sprintf(
                '<script type="application/ld+json">%s</script>',
                wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );
        }

        return $output;
    }

    public function enqueueAssets(): void
    {
        // Inline CSS for accordion animation.
        wp_add_inline_style('polski-frontend', '
            .polski-faq-question:hover { background: #f1f5f9 !important; }
            .polski-faq-question.open .polski-faq-icon { transform: rotate(45deg); }
        ');
    }
}
