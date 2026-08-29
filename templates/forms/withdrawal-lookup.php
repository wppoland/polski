<?php
/**
 * Public lookup form for the guest withdrawal flow.
 *
 * Designed with accessibility and discoverability in mind:
 *  - semantic <section> landmark with aria-label,
 *  - live notice region (role=status) reachable by screen readers,
 *  - labelled fields with autocomplete hints, aria-required, aria-describedby,
 *  - text width clamped to ~65ch (cognitive-load reduction; Bovelett clarity dividend),
 *  - SEO-rich intro paragraph (~200 words covering the directive, the deadline,
 *    the merchant, and what the consumer will do next),
 *  - FAQPage JSON-LD so search engines surface this page for common withdrawal queries.
 *
 * @var string                                      $polski_nonce
 * @var array{type: string, message: string}|null   $polski_notice
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$polski_general = get_option('polski_general', []);
$polski_general = is_array($polski_general) ? $polski_general : [];
$polski_merchant = trim((string) ($polski_general['company_name'] ?? get_bloginfo('name')));

$polski_settings = get_option('polski_withdrawal', []);
$polski_settings = is_array($polski_settings) ? $polski_settings : [];
$polski_days = isset($polski_settings['period_days']) ? max(1, (int) $polski_settings['period_days']) : 14;

// Sticky form values: preserve user input across error redirects. The nonce
// associated with these POST values is verified by GuestWithdrawalService
// before any side-effect; here we only echo the raw input back (sanitised).
// phpcs:disable WordPress.Security.NonceVerification.Missing -- Sticky echo only; side-effect path verifies the nonce.
$polski_sticky_order = isset($_POST['polski_order_number']) ? sanitize_text_field(wp_unslash((string) $_POST['polski_order_number'])) : '';
$polski_sticky_email = isset($_POST['polski_email']) ? sanitize_email(wp_unslash((string) $_POST['polski_email'])) : '';
// phpcs:enable WordPress.Security.NonceVerification.Missing

$polski_has_error = $polski_notice !== null && ($polski_notice['type'] ?? '') === 'error';
?>
<a href="#polski-withdrawal-lookup-form" class="polski-withdrawal-skip-link">
    <?php esc_html_e('Go to the withdrawal form', 'polski'); ?>
</a>

<section
    class="polski-withdrawal-lookup"
    aria-labelledby="polski-withdrawal-lookup-title"
    lang="pl"
    style="max-width: 65ch;"
>
    <h2 id="polski-withdrawal-lookup-title">
        <?php esc_html_e('Withdrawal from the contract, online form', 'polski'); ?>
    </h2>

    <p class="polski-withdrawal-lookup__intro">
        <?php
        printf(
            /* translators: 1: merchant name, 2: number of days */
            esc_html__(
                'Bought from %1$s as a consumer? You have the right to withdraw from a distance contract without giving a reason within %2$d days of receiving your order. You do not need to log in to file the declaration: just enter below the email address used for the purchase and the order number.',
                'polski',
            ),
            esc_html($polski_merchant),
            (int) $polski_days,
        );
        ?>
    </p>

    <details class="polski-withdrawal-lookup__more" style="margin: 0.5rem 0 1.5rem;">
        <summary style="cursor: pointer; color: #1d4ed8;">
            <?php esc_html_e('How does it work? (expand)', 'polski'); ?>
        </summary>
        <div style="padding: 0.75rem 0 0;">
            <p>
                <?php esc_html_e('Once we have checked the details we will email a single-use link that opens the withdrawal form. The link is valid for 30 minutes and works only once.', 'polski'); ?>
            </p>
            <p>
                <?php esc_html_e('In the next step you choose whether to return the whole order or only some items; you can also withdraw from individual units.', 'polski'); ?>
            </p>
            <p>
                <?php
                printf(
                    /* translators: %s = directive reference */
                    esc_html__('After you submit the declaration you will get an email with the declaration number, the date it was filed and a summary of the order. The right comes from Article 27 of the Polish Consumer Rights Act, which implements Directive %s.', 'polski'),
                    esc_html__('2011/83/EU (as amended by 2023/2673)', 'polski'),
                );
                ?>
            </p>
        </div>
    </details>

    <?php if ($polski_notice !== null) : ?>
        <div
            class="polski-withdrawal-notice polski-withdrawal-notice--<?php echo esc_attr($polski_notice['type']); ?>"
            role="<?php echo $polski_notice['type'] === 'error' ? 'alert' : 'status'; ?>"
            aria-live="<?php echo $polski_notice['type'] === 'error' ? 'assertive' : 'polite'; ?>"
            tabindex="-1"
        >
            <?php echo esc_html($polski_notice['message']); ?>
        </div>
    <?php endif; ?>

    <form id="polski-withdrawal-lookup-form" method="post" action="" novalidate aria-describedby="polski-withdrawal-lookup-help">
        <p>
            <label for="polski_order_number">
                <?php esc_html_e('Order number', 'polski'); ?>
                <span aria-hidden="true" style="color:#b91c1c;">*</span>
            </label>
            <input
                type="text"
                id="polski_order_number"
                name="polski_order_number"
                inputmode="numeric"
                autocomplete="off"
                aria-required="true"
                aria-describedby="polski_order_number_help"
                aria-invalid="<?php echo $polski_has_error && $polski_sticky_order === '' ? 'true' : 'false'; ?>"
                value="<?php echo esc_attr($polski_sticky_order); ?>"
                required
            >
            <small id="polski_order_number_help" style="display:block; color:#475569;">
                <?php esc_html_e('The number is in your purchase confirmation email, on the "Your order #..." line.', 'polski'); ?>
            </small>
        </p>

        <p>
            <label for="polski_email">
                <?php esc_html_e('Email address used for the purchase', 'polski'); ?>
                <span aria-hidden="true" style="color:#b91c1c;">*</span>
            </label>
            <input
                type="email"
                id="polski_email"
                name="polski_email"
                autocomplete="email"
                inputmode="email"
                aria-required="true"
                aria-describedby="polski_email_help"
                aria-invalid="<?php echo $polski_has_error && $polski_sticky_email === '' ? 'true' : 'false'; ?>"
                value="<?php echo esc_attr($polski_sticky_email); ?>"
                required
            >
            <small id="polski_email_help" style="display:block; color:#475569;">
                <?php esc_html_e('We will email a secure link to this address that opens the withdrawal form.', 'polski'); ?>
            </small>
        </p>

        <p id="polski-withdrawal-lookup-help" class="screen-reader-text" style="position:absolute;left:-9999px;">
            <?php esc_html_e('All fields are required.', 'polski'); ?>
        </p>

        <p>
            <input type="hidden" name="polski_lookup_nonce" value="<?php echo esc_attr($polski_nonce); ?>">
            <button
                type="submit"
                name="polski_withdrawal_lookup"
                value="1"
                class="button button-primary"
            >
                <?php esc_html_e('Email me the link to the form', 'polski'); ?>
            </button>
        </p>

        <p style="color:#475569; font-size: 0.9rem;">
            <?php esc_html_e('The link is valid for 30 minutes and works only once. If the message does not arrive, check your spam folder and enter the address again.', 'polski'); ?>
        </p>
    </form>

    <?php
    // Visible FAQ - mirrors the FAQPage JSON-LD so it benefits cognitive users, not just search engines.
    $polski_faq_visible = [
        ['q' => __('How long do I have to withdraw from the contract?', 'polski'), 'a' => sprintf(/* translators: %d = days */ __('By default you have %d days from the day you received the order. The period runs from the day the goods came into your possession, or into the possession of a third party other than the carrier.', 'polski'), $polski_days)],
        ['q' => __('Can I return only some of the products?', 'polski'), 'a' => __('Yes. Once the form is open you choose which items the withdrawal covers. You can withdraw from any number of units and the rest stay in the order.', 'polski')],
        ['q' => __('What happens after I send the form?', 'polski'), 'a' => __('You will get a confirmation email with a unique declaration number and a full summary of the order. Then send the goods back to the shop\'s address within 14 days of filing the declaration.', 'polski')],
        ['q' => __('Are there products that cannot be returned?', 'polski'), 'a' => __('Yes. Under Article 38 of the Polish Consumer Rights Act the right of withdrawal does not cover, among others, goods made to the consumer\'s specification, goods that deteriorate rapidly, and goods sealed for hygiene reasons once the seal is broken.', 'polski')],
    ];
    ?>
    <section aria-labelledby="polski-withdrawal-faq-title" style="margin-top: 2rem;">
        <h3 id="polski-withdrawal-faq-title"><?php esc_html_e('Common questions', 'polski'); ?></h3>
        <?php foreach ($polski_faq_visible as $polski_qa) : ?>
            <details style="margin: 0.5rem 0; border-left: 3px solid #e2e8f0; padding-left: 0.75rem;">
                <summary style="cursor: pointer; font-weight: 600;">
                    <?php echo esc_html($polski_qa['q']); ?>
                </summary>
                <p style="margin: 0.5rem 0 0;"><?php echo esc_html($polski_qa['a']); ?></p>
            </details>
        <?php endforeach; ?>
    </section>

    <p style="margin-top: 1.5rem; color: #475569;">
        <?php
        $polski_support_email = '';
        if (! empty($polski_general['company_email'])) {
            $polski_support_email = (string) $polski_general['company_email'];
        } elseif (function_exists('get_option')) {
            $polski_support_email = (string) get_option('admin_email', '');
        }
        if ($polski_support_email !== '') {
            printf(
                /* translators: %s = support email link */
                esc_html__('Having trouble with the form? Write to %s and we will help you file the declaration by hand.', 'polski'),
                '<a href="' . esc_url('mailto:' . $polski_support_email) . '">' . esc_html($polski_support_email) . '</a>',
            );
        }
        ?>
    </p>
</section>

<?php
// FAQPage schema - surfaced once per page render, helps search engines and AI
// assistants answer common withdrawal questions about this store. Cached as a
// static guard to avoid double-printing if the shortcode appears twice.
if (! isset($GLOBALS['polski_withdrawal_lookup_schema_emitted'])) {
    $GLOBALS['polski_withdrawal_lookup_schema_emitted'] = true;

    /* translators: %s: merchant / shop name */
    $polski_faq_q_who = __('Who can withdraw from a contract at %s?', 'polski');
    /* translators: %d: withdrawal period in days (typically 14) */
    $polski_faq_a_when = __('By default you have %d days from the day you received the order to file the declaration. The period runs from the day the goods came into your possession, or into the possession of a third party indicated by you other than the carrier.', 'polski');

    $polski_faq = [
        [
            'q' => sprintf($polski_faq_q_who, $polski_merchant),
            'a' => __('Any consumer, meaning a natural person buying for purposes outside their trade or business. When you buy as a company, the right to withdraw without giving a reason applies only in a limited form.', 'polski'),
        ],
        [
            'q' => __('How long do I have to withdraw from the contract?', 'polski'),
            'a' => sprintf($polski_faq_a_when, $polski_days),
        ],
        [
            'q' => __('Can I return only some of the products in the order?', 'polski'),
            'a' => __('Yes. Once the form is open you choose which items the withdrawal covers: every product, a single product, or just some of the units. The rest stay in the order.', 'polski'),
        ],
        [
            'q' => __('What happens after I send the form?', 'polski'),
            'a' => __('You will get an email confirming that the declaration was accepted, with a unique declaration number and a full summary of the order. Then send the goods back to the shop\'s address within 14 days of filing the declaration.', 'polski'),
        ],
        [
            'q' => __('Are there products that cannot be returned?', 'polski'),
            'a' => __('Yes. Under Article 38 of the Polish Consumer Rights Act the right of withdrawal does not cover, among others, goods made to the consumer\'s specification, goods that deteriorate rapidly, goods sealed for hygiene reasons once the seal is broken, and digital content supplied with the consumer\'s express consent before the withdrawal period ended.', 'polski'),
        ],
    ];

    $polski_schema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static function (array $item): array {
            return [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['a'],
                ],
            ];
        }, $polski_faq),
    ];

    echo '<script type="application/ld+json">' . wp_json_encode($polski_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
