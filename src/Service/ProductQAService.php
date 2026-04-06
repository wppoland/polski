<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Product Questions & Answers - Amazon-style Q&A on product pages.
 *
 * Uses WordPress comments with type 'product_question' and 'product_answer'.
 * Questions displayed as an accordion on product pages below reviews.
 * Store owner gets email notification for new questions.
 * Schema.org QAPage markup for SEO.
 * Voting on answers (helpful/not helpful).
 */
final class ProductQAService implements HasHooks
{
    private const COMMENT_TYPE_Q = 'product_question';
    private const COMMENT_TYPE_A = 'product_answer';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('product_qa')) {
            return;
        }

        // Display Q&A section on product page.
        add_action('woocommerce_after_single_product_summary', [$this, 'renderQASection'], 25);

        // Handle question submission.
        add_action('wp_loaded', [$this, 'handleQuestionSubmit']);

        // Handle answer submission (admin).
        add_action('wp_loaded', [$this, 'handleAnswerSubmit']);

        // Handle vote.
        add_action('wp_ajax_polski_qa_vote', [$this, 'handleVote']);
        add_action('wp_ajax_nopriv_polski_qa_vote', [$this, 'handleVote']);

        // Admin notification for new questions.
        add_action('comment_post', [$this, 'notifyAdminOnQuestion'], 10, 3);

        // Schema.org markup.
        add_action('wp_footer', [$this, 'outputSchema']);

        // Add Q&A count to product tabs.
        add_filter('woocommerce_product_tabs', [$this, 'addProductTab']);
    }

    /**
     * Add a Q&A tab to product page.
     *
     * @param array<string, array<string, mixed>> $tabs
     * @return array<string, array<string, mixed>>
     */
    public function addProductTab(array $tabs): array
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return $tabs;
        }

        $count = $this->getQuestionCount($product->get_id());

        $tabs['product_qa'] = [
            'title' => sprintf(
                /* translators: %d: number of questions */
                __('Questions (%d)', 'polski'),
                $count,
            ),
            'priority' => 25,
            'callback' => [$this, 'renderTabContent'],
        ];

        return $tabs;
    }

    /**
     * Render Q&A content inside the product tab.
     */
    public function renderTabContent(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $this->renderQAContent($product);
    }

    /**
     * Render Q&A section (standalone, for hook usage).
     */
    public function renderQASection(): void
    {
        // Skip if we're using tabs (to avoid duplicate).
        // The tab callback handles the content.
    }

    private function renderQAContent(\WC_Product $product): void
    {
        $productId = $product->get_id();
        $questions = $this->getQuestions($productId);

        // Ask a question form.
        echo '<div class="polski-qa-section">';

        if (! empty($questions)) {
            echo '<div class="polski-qa-list" style="margin-bottom:24px">';

            foreach ($questions as $question) {
                $answers = $this->getAnswers((int) $question->comment_ID);
                $answerCount = count($answers);

                echo '<div class="polski-qa-item" style="border:1px solid #e2e8f0;border-radius:8px;margin-bottom:8px;overflow:hidden">';

                // Question.
                $questionDateTs = strtotime((string) $question->comment_date);
                $questionDateFormatted = wp_date(get_option('date_format'), $questionDateTs !== false ? $questionDateTs : null);
                printf(
                    '<div style="padding:12px 16px;background:#f8fafc;display:flex;gap:8px;align-items:flex-start"><span style="color:#0369a1;font-weight:700;font-size:16px">Q</span><div><div style="font-weight:600">%s</div><div style="font-size:12px;color:#94a3b8;margin-top:4px">%s - %s</div></div></div>',
                    esc_html((string) $question->comment_content),
                    esc_html((string) $question->comment_author),
                    esc_html(is_string($questionDateFormatted) ? $questionDateFormatted : ''),
                );

                // Answers.
                foreach ($answers as $answer) {
                    $votes = (int) get_comment_meta((int) $answer->comment_ID, '_polski_qa_votes', true);
                    $isAdmin = user_can((int) $answer->user_id, 'manage_woocommerce');
                    $authorBadge = $isAdmin ? ' <span style="background:#0369a1;color:#fff;padding:1px 6px;border-radius:3px;font-size:10px">' . esc_html__('Store', 'polski') . '</span>' : '';

                    $answerDateTs = strtotime((string) $answer->comment_date);
                    $answerDateFormatted = wp_date(get_option('date_format'), $answerDateTs !== false ? $answerDateTs : null);
                    printf(
                        '<div style="padding:12px 16px;border-top:1px solid #f1f5f9;display:flex;gap:8px;align-items:flex-start"><span style="color:#16a34a;font-weight:700;font-size:16px">A</span><div style="flex:1"><div>%s</div><div style="font-size:12px;color:#94a3b8;margin-top:4px">%s%s - %s | <button onclick="polskiQaVote(%d)" style="background:none;border:none;cursor:pointer;color:#64748b;font-size:12px">%s (%d)</button></div></div></div>',
                        wp_kses_post((string) $answer->comment_content),
                        esc_html((string) $answer->comment_author),
                        wp_kses_post($authorBadge),
                        esc_html(is_string($answerDateFormatted) ? $answerDateFormatted : ''),
                        (int) $answer->comment_ID,
                        esc_html__('Helpful', 'polski'),
                        (int) $votes,
                    );
                }

                // Answer form (for logged-in users).
                if (is_user_logged_in()) {
                    echo '<div style="padding:12px 16px;border-top:1px solid #f1f5f9;background:#fafbfc">';
                    echo '<form method="post" style="display:flex;gap:8px">';
                    wp_nonce_field('polski_qa_answer', '_polski_qa_answer_nonce');
                    printf('<input type="hidden" name="question_id" value="%d">', (int) $question->comment_ID);
                    printf('<input type="hidden" name="product_id" value="%d">', (int) $productId);
                    echo '<input type="hidden" name="polski_qa_action" value="answer">';
                    printf('<input type="text" name="answer_text" placeholder="%s" required style="flex:1;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">', esc_attr__('Write an answer...', 'polski'));
                    printf('<button type="submit" style="padding:8px 16px;background:#16a34a;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;white-space:nowrap">%s</button>', esc_html__('Answer', 'polski'));
                    echo '</form></div>';
                }

                echo '</div>';
            }

            echo '</div>';
        }

        // Ask a question form.
        echo '<div class="polski-qa-ask" style="margin-top:16px">';
        printf('<h4 style="margin-bottom:8px">%s</h4>', esc_html__('Ask a question', 'polski'));
        echo '<form method="post" style="display:flex;gap:8px">';
        wp_nonce_field('polski_qa_question', '_polski_qa_question_nonce');
        printf('<input type="hidden" name="product_id" value="%d">', (int) $productId);
        echo '<input type="hidden" name="polski_qa_action" value="question">';
        printf('<input type="text" name="question_text" placeholder="%s" required style="flex:1;padding:10px 14px;border:1px solid #d1d5db;border-radius:6px;font-size:14px">', esc_attr__('Your question about this product...', 'polski'));

        if (! is_user_logged_in()) {
            printf('<input type="email" name="question_email" placeholder="%s" required style="width:200px;padding:10px 14px;border:1px solid #d1d5db;border-radius:6px;font-size:14px">', esc_attr__('Your email', 'polski'));
        }

        printf('<button type="submit" style="padding:10px 20px;background:#0369a1;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:600;white-space:nowrap">%s</button>', esc_html__('Ask', 'polski'));
        echo '</form></div>';

        // Vote JS.
        echo '<script>function polskiQaVote(id){jQuery.post("' . esc_js(admin_url('admin-ajax.php')) . '",{action:"polski_qa_vote",comment_id:id,nonce:"' . esc_js(wp_create_nonce('polski_qa_vote')) . '"},function(r){if(r.success)location.reload()})}</script>';

        echo '</div>';
    }

    // ── Handlers ────────────────────────────────────────

    public function handleQuestionSubmit(): void
    {
        if (empty($_POST['polski_qa_action']) || $_POST['polski_qa_action'] !== 'question') {
            return;
        }

        if (! isset($_POST['_polski_qa_question_nonce']) || ! wp_verify_nonce($_POST['_polski_qa_question_nonce'], 'polski_qa_question')) {
            return;
        }

        $productId = absint($_POST['product_id'] ?? 0);
        $text = sanitize_textarea_field($_POST['question_text'] ?? '');

        if ($productId <= 0 || empty($text)) {
            return;
        }

        $commentData = [
            'comment_post_ID' => $productId,
            'comment_content' => $text,
            'comment_type' => self::COMMENT_TYPE_Q,
            'comment_approved' => 1,
        ];

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $commentData['user_id'] = $user->ID;
            $commentData['comment_author'] = $user->display_name;
            $commentData['comment_author_email'] = $user->user_email;
        } else {
            $commentData['comment_author'] = __('Customer', 'polski');
            $commentData['comment_author_email'] = sanitize_email($_POST['question_email'] ?? '');
        }

        wp_insert_comment($commentData);

        wp_safe_redirect(get_permalink($productId) . '#tab-product_qa');
        exit;
    }

    public function handleAnswerSubmit(): void
    {
        if (empty($_POST['polski_qa_action']) || $_POST['polski_qa_action'] !== 'answer') {
            return;
        }

        if (! isset($_POST['_polski_qa_answer_nonce']) || ! wp_verify_nonce($_POST['_polski_qa_answer_nonce'], 'polski_qa_answer')) {
            return;
        }

        if (! is_user_logged_in()) {
            return;
        }

        $questionId = absint($_POST['question_id'] ?? 0);
        $productId = absint($_POST['product_id'] ?? 0);
        $text = sanitize_textarea_field($_POST['answer_text'] ?? '');

        if ($questionId <= 0 || $productId <= 0 || empty($text)) {
            return;
        }

        $user = wp_get_current_user();

        wp_insert_comment([
            'comment_post_ID' => $productId,
            'comment_parent' => $questionId,
            'comment_content' => $text,
            'comment_type' => self::COMMENT_TYPE_A,
            'comment_approved' => 1,
            'user_id' => $user->ID,
            'comment_author' => $user->display_name,
            'comment_author_email' => $user->user_email,
        ]);

        wp_safe_redirect(get_permalink($productId) . '#tab-product_qa');
        exit;
    }

    public function handleVote(): void
    {
        check_ajax_referer('polski_qa_vote', 'nonce');

        $commentId = absint($_POST['comment_id'] ?? 0);

        if ($commentId <= 0) {
            wp_send_json_error();
        }

        $votes = (int) get_comment_meta($commentId, '_polski_qa_votes', true);
        update_comment_meta($commentId, '_polski_qa_votes', $votes + 1);

        wp_send_json_success(['votes' => $votes + 1]);
    }

    /**
     * @param int            $commentId
     * @param int|string     $approved
     * @param array<string, mixed> $commentData
     */
    public function notifyAdminOnQuestion($commentId, $approved, $commentData): void
    {
        if (($commentData['comment_type'] ?? '') !== self::COMMENT_TYPE_Q) {
            return;
        }

        $product = wc_get_product((int) $commentData['comment_post_ID']);

        if (! $product) {
            return;
        }

        $adminEmail = get_option('admin_email');
        $subject = sprintf(
            /* translators: %s: product name */
            __('New question about: %s', 'polski'),
            $product->get_name(),
        );
        $message = sprintf(
            '<p><strong>%s</strong></p><p>%s</p><p><a href="%s">%s</a></p>',
            esc_html($commentData['comment_content'] ?? ''),
            esc_html(sprintf(
                /* translators: %s: name of the person who asked the question */
                __('Asked by: %s', 'polski'),
                $commentData['comment_author'] ?? '',
            )),
            esc_url(get_edit_post_link($product->get_id()) . '#tab-product_qa'),
            esc_html__('Answer this question', 'polski'),
        );

        wp_mail($adminEmail, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }

    // ── Schema.org ──────────────────────────────────────

    public function outputSchema(): void
    {
        if (! is_product()) {
            return;
        }

        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $questions = $this->getQuestions($product->get_id());

        if (empty($questions)) {
            return;
        }

        $schemaQuestions = [];

        foreach ($questions as $question) {
            $answers = $this->getAnswers((int) $question->comment_ID);
            $schemaAnswers = [];

            foreach ($answers as $answer) {
                $votes = (int) get_comment_meta((int) $answer->comment_ID, '_polski_qa_votes', true);
                $schemaAnswers[] = [
                    '@type' => 'Answer',
                    'text' => $answer->comment_content,
                    'dateCreated' => $answer->comment_date,
                    'upvoteCount' => $votes,
                    'author' => ['@type' => 'Person', 'name' => $answer->comment_author],
                ];
            }

            if (empty($schemaAnswers)) {
                continue;
            }

            $schemaQuestions[] = [
                '@type' => 'Question',
                'name' => $question->comment_content,
                'text' => $question->comment_content,
                'dateCreated' => $question->comment_date,
                'author' => ['@type' => 'Person', 'name' => $question->comment_author],
                'answerCount' => count($schemaAnswers),
                'acceptedAnswer' => $schemaAnswers[0],
                'suggestedAnswer' => array_slice($schemaAnswers, 1),
            ];
        }

        if (empty($schemaQuestions)) {
            return;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'QAPage',
            'mainEntity' => $schemaQuestions,
        ];

        printf(
            '<script type="application/ld+json">%s</script>' . "\n",
            wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    // ── Queries ──────────────────────────────────────────

    /**
     * @return list<\WP_Comment>
     */
    private function getQuestions(int $productId): array
    {
        $comments = get_comments([
            'post_id' => $productId,
            'type' => self::COMMENT_TYPE_Q,
            'parent' => 0,
            'status' => 'approve',
            'orderby' => 'comment_date',
            'order' => 'DESC',
            'number' => 50,
        ]);

        return is_array($comments)
            ? array_values(array_filter($comments, static fn ($c): bool => $c instanceof \WP_Comment))
            : [];
    }

    /**
     * @return list<\WP_Comment>
     */
    private function getAnswers(int $questionId): array
    {
        $comments = get_comments([
            'type' => self::COMMENT_TYPE_A,
            'parent' => $questionId,
            'status' => 'approve',
            'orderby' => 'comment_date',
            'order' => 'ASC',
        ]);

        return is_array($comments)
            ? array_values(array_filter($comments, static fn ($c): bool => $c instanceof \WP_Comment))
            : [];
    }

    private function getQuestionCount(int $productId): int
    {
        return (int) get_comments([
            'post_id' => $productId,
            'type' => self::COMMENT_TYPE_Q,
            'parent' => 0,
            'status' => 'approve',
            'count' => true,
        ]);
    }
}
