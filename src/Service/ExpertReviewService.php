<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Expert product reviews - custom post type for editorial reviews.
 *
 * Creates a dedicated CPT for expert/editorial reviews linked to products.
 * Displays reviews on product pages with rating, author info, and rich schema.
 * Good for SEO authority and differentiation from customer reviews.
 */
final class ExpertReviewService implements HasHooks
{
    private const CPT = 'expert_review';
    private const META_PRODUCT = '_expert_review_product_id';
    private const META_RATING = '_expert_review_rating';
    private const META_VERDICT = '_expert_review_verdict';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('expert_reviews')) {
            return;
        }

        add_action('init', [$this, 'registerPostType']);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_' . self::CPT, [$this, 'saveMetaBoxes']);

        // Display on product page.
        add_action('woocommerce_after_single_product_summary', [$this, 'displayOnProduct'], 15);

        // Schema.org markup.
        add_action('wp_footer', [$this, 'outputSchema']);
    }

    public function registerPostType(): void
    {
        register_post_type(self::CPT, [
            'labels' => [
                'name' => __('Expert Reviews', 'polski'),
                'singular_name' => __('Expert Review', 'polski'),
                'add_new' => __('Add review', 'polski'),
                'add_new_item' => __('Add expert review', 'polski'),
                'edit_item' => __('Edit expert review', 'polski'),
                'search_items' => __('Search expert reviews', 'polski'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=product',
            'supports' => ['title', 'editor', 'thumbnail', 'author'],
            'menu_icon' => 'dashicons-star-filled',
            'capability_type' => 'post',
        ]);
    }

    public function addMetaBoxes(): void
    {
        add_meta_box(
            'expert_review_details',
            __('Review Details', 'polski'),
            [$this, 'renderMetaBox'],
            self::CPT,
            'side',
            'high',
        );
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        wp_nonce_field('polski_expert_review', '_polski_er_nonce');

        $productId = (int) get_post_meta($post->ID, self::META_PRODUCT, true);
        $rating = (float) get_post_meta($post->ID, self::META_RATING, true);
        $verdict = get_post_meta($post->ID, self::META_VERDICT, true);

        // Product selector.
        echo '<p><label><strong>' . esc_html__('Product', 'polski') . '</strong></label><br>';
        echo '<select name="expert_review_product_id" style="width:100%">';
        echo '<option value="">' . esc_html__('Select product...', 'polski') . '</option>';

        $products = wc_get_products(['limit' => -1, 'status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']);
        $products = is_array($products) ? $products : [];

        foreach ($products as $product) {
            echo '<option value="' . esc_attr((string) $product->get_id()) . '"'
                . selected($productId, $product->get_id(), false) . '>'
                . esc_html($product->get_name())
                . '</option>';
        }

        echo '</select></p>';

        // Rating.
        printf(
            '<p><label><strong>%s</strong></label><br><input type="number" name="expert_review_rating" value="%s" min="1" max="10" step="0.5" style="width:80px"> / 10</p>',
            esc_html__('Rating', 'polski'),
            esc_attr($rating > 0 ? (string) $rating : ''),
        );

        // Verdict.
        printf(
            '<p><label><strong>%s</strong></label><br><input type="text" name="expert_review_verdict" value="%s" style="width:100%%" placeholder="%s"></p>',
            esc_html__('Verdict', 'polski'),
            esc_attr($verdict),
            esc_attr__('e.g. Recommended, Best in class', 'polski'),
        );
    }

    public function saveMetaBoxes(int $postId): void
    {
        if (! isset($_POST['_polski_er_nonce']) || ! wp_verify_nonce($_POST['_polski_er_nonce'], 'polski_expert_review')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $productId = absint($_POST['expert_review_product_id'] ?? 0);
        $rating = min(10, max(0, (float) ($_POST['expert_review_rating'] ?? 0)));
        $verdict = sanitize_text_field($_POST['expert_review_verdict'] ?? '');

        update_post_meta($postId, self::META_PRODUCT, $productId);
        update_post_meta($postId, self::META_RATING, $rating);
        update_post_meta($postId, self::META_VERDICT, $verdict);
    }

    /**
     * Display expert reviews on the single product page.
     */
    public function displayOnProduct(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $reviews = $this->getReviewsForProduct($product->get_id());

        if (empty($reviews)) {
            return;
        }

        echo '<div class="polski-expert-reviews" style="margin:32px 0">';
        printf('<h2>%s</h2>', esc_html__('Expert Reviews', 'polski'));

        foreach ($reviews as $review) {
            $rating = (float) get_post_meta($review->ID, self::META_RATING, true);
            $verdict = get_post_meta($review->ID, self::META_VERDICT, true);
            $author = get_the_author_meta('display_name', (int) $review->post_author);
            $date = get_the_date('', $review);
            $thumbnail = get_the_post_thumbnail($review->ID, 'thumbnail');

            echo '<div class="polski-expert-review" style="border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin-bottom:16px">';

            // Header.
            echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">';
            printf('<div><strong>%s</strong> <span style="color:#64748b">%s %s</span></div>', esc_html($review->post_title), esc_html__('by', 'polski'), esc_html($author));

            if ($rating > 0) {
                $color = $rating >= 8 ? '#16a34a' : ($rating >= 5 ? '#ca8a04' : '#dc2626');
                printf(
                    '<div style="background:%s;color:#fff;padding:4px 12px;border-radius:6px;font-weight:700;font-size:18px">%s/10</div>',
                    esc_attr($color),
                    esc_html(number_format($rating, 1)),
                );
            }

            echo '</div>';

            // Content.
            echo '<div class="polski-expert-review-content">' . wp_kses_post(wpautop($review->post_content)) . '</div>';

            // Verdict.
            if (! empty($verdict)) {
                printf(
                    '<div style="margin-top:12px;padding:8px 12px;background:#f0f9ff;border-radius:6px"><strong>%s:</strong> %s</div>',
                    esc_html__('Verdict', 'polski'),
                    esc_html(is_string($verdict) ? $verdict : ''),
                );
            }

            printf('<div style="color:#94a3b8;font-size:12px;margin-top:8px">%s</div>', esc_html(is_scalar($date) ? (string) $date : ''));

            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Output Schema.org Review markup.
     */
    public function outputSchema(): void
    {
        if (! is_product()) {
            return;
        }

        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $reviews = $this->getReviewsForProduct($product->get_id());

        if (empty($reviews)) {
            return;
        }

        $schemaReviews = [];

        foreach ($reviews as $review) {
            $rating = (float) get_post_meta($review->ID, self::META_RATING, true);
            $author = get_the_author_meta('display_name', (int) $review->post_author);

            $schemaReview = [
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => $author],
                'datePublished' => get_the_date('c', $review),
                'reviewBody' => wp_strip_all_tags($review->post_content),
                'name' => $review->post_title,
            ];

            if ($rating > 0) {
                $schemaReview['reviewRating'] = [
                    '@type' => 'Rating',
                    'ratingValue' => $rating,
                    'bestRating' => 10,
                    'worstRating' => 1,
                ];
            }

            $schemaReviews[] = $schemaReview;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->get_name(),
            'review' => $schemaReviews,
        ];

        printf(
            '<script type="application/ld+json">%s</script>' . "\n",
            wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * @return list<\WP_Post>
     */
    private function getReviewsForProduct(int $productId): array
    {
        $query = new \WP_Query([
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'meta_key' => self::META_PRODUCT,
            'meta_value' => $productId,
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $posts = is_array($query->posts) ? $query->posts : [];

        return array_values(array_filter($posts, static fn ($post): bool => $post instanceof \WP_Post));
    }
}
