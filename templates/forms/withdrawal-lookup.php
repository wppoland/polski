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
    <?php esc_html_e('Przejdź do formularza odstąpienia', 'polski'); ?>
</a>

<section
    class="polski-withdrawal-lookup"
    aria-labelledby="polski-withdrawal-lookup-title"
    lang="pl"
    style="max-width: 65ch;"
>
    <h2 id="polski-withdrawal-lookup-title">
        <?php esc_html_e('Odstąpienie od umowy - formularz online', 'polski'); ?>
    </h2>

    <p class="polski-withdrawal-lookup__intro">
        <?php
        printf(
            /* translators: 1: merchant name, 2: number of days */
            esc_html__(
                'Jesteś konsumentem i kupiłeś u sprzedawcy %1$s? Masz prawo odstąpić od umowy zawartej na odległość bez podawania przyczyny w terminie %2$d dni od dnia, w którym otrzymałeś zamówienie. Aby złożyć oświadczenie, nie musisz logować się - wystarczy, że poniżej podasz adres e-mail użyty przy zakupie oraz numer zamówienia.',
                'polski',
            ),
            esc_html($polski_merchant),
            (int) $polski_days,
        );
        ?>
    </p>

    <details class="polski-withdrawal-lookup__more" style="margin: 0.5rem 0 1.5rem;">
        <summary style="cursor: pointer; color: #1d4ed8;">
            <?php esc_html_e('Jak to działa? (rozwiń)', 'polski'); ?>
        </summary>
        <div style="padding: 0.75rem 0 0;">
            <p>
                <?php esc_html_e('Po weryfikacji wyślemy na podany adres jednorazowy link, który otworzy formularz odstąpienia. Link będzie ważny przez 30 minut i można go użyć tylko raz.', 'polski'); ?>
            </p>
            <p>
                <?php esc_html_e('W kolejnym kroku wybierzesz, czy chcesz zwrócić całe zamówienie, czy tylko wybrane pozycje (możliwe są też częściowe odstąpienia od pojedynczych sztuk).', 'polski'); ?>
            </p>
            <p>
                <?php
                printf(
                    /* translators: %s = directive reference */
                    esc_html__('Po przesłaniu oświadczenia otrzymasz e-mail z numerem deklaracji, datą złożenia i podsumowaniem zamówienia. Uprawnienie wynika z art. 27 ustawy o prawach konsumenta wdrażającej dyrektywę %s.', 'polski'),
                    '2011/83/UE (zmienionej przez 2023/2673)',
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
                <?php esc_html_e('Numer zamówienia', 'polski'); ?>
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
                <?php esc_html_e('Numer znajdziesz w e-mailu potwierdzającym zakup, w pozycji „Twoje zamówienie #…”.', 'polski'); ?>
            </small>
        </p>

        <p>
            <label for="polski_email">
                <?php esc_html_e('Adres e-mail użyty przy zakupie', 'polski'); ?>
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
                <?php esc_html_e('Wyślemy na ten adres bezpieczny link otwierający formularz odstąpienia.', 'polski'); ?>
            </small>
        </p>

        <p id="polski-withdrawal-lookup-help" class="screen-reader-text" style="position:absolute;left:-9999px;">
            <?php esc_html_e('Wszystkie pola są wymagane.', 'polski'); ?>
        </p>

        <p>
            <input type="hidden" name="polski_lookup_nonce" value="<?php echo esc_attr($polski_nonce); ?>">
            <button
                type="submit"
                name="polski_withdrawal_lookup"
                value="1"
                class="button button-primary"
            >
                <?php esc_html_e('Wyślij link do formularza', 'polski'); ?>
            </button>
        </p>

        <p style="color:#475569; font-size: 0.9rem;">
            <?php esc_html_e('Link będzie ważny przez 30 minut i można go użyć tylko raz. Jeśli nie otrzymasz wiadomości, sprawdź folder Spam i wpisz adres ponownie.', 'polski'); ?>
        </p>
    </form>

    <?php
    // Visible FAQ - mirrors the FAQPage JSON-LD so it benefits cognitive users, not just search engines.
    $polski_faq_visible = [
        ['q' => __('W jakim terminie mogę odstąpić od umowy?', 'polski'), 'a' => sprintf(/* translators: %d = days */ __('Domyślnie masz %d dni od dnia otrzymania zamówienia. Termin biegnie od dnia, w którym towar znalazł się w Twoim posiadaniu lub w posiadaniu osoby trzeciej innej niż przewoźnik.', 'polski'), $polski_days)],
        ['q' => __('Czy mogę zwrócić tylko niektóre produkty?', 'polski'), 'a' => __('Tak. Po otwarciu formularza wybierzesz, których pozycji ma dotyczyć odstąpienie. Można odstąpić od wybranej liczby sztuk - pozostałe pozostaną w zamówieniu.', 'polski')],
        ['q' => __('Co się stanie po wysłaniu formularza?', 'polski'), 'a' => __('Otrzymasz e-mail potwierdzający z unikalnym numerem deklaracji i pełnym podsumowaniem zamówienia. Następnie odeślij produkty na adres sklepu w terminie 14 dni od złożenia oświadczenia.', 'polski')],
        ['q' => __('Czy są produkty, których nie można zwrócić?', 'polski'), 'a' => __('Tak. Zgodnie z art. 38 ustawy o prawach konsumenta z prawa odstąpienia wyłączone są m.in. produkty wykonane na zamówienie indywidualne, szybko psujące się, oraz zapieczętowane ze względów higienicznych po otwarciu opakowania.', 'polski')],
    ];
    ?>
    <section aria-labelledby="polski-withdrawal-faq-title" style="margin-top: 2rem;">
        <h3 id="polski-withdrawal-faq-title"><?php esc_html_e('Najczęstsze pytania', 'polski'); ?></h3>
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
                esc_html__('Masz problem z formularzem? Napisz na %s - pomożemy złożyć oświadczenie ręcznie.', 'polski'),
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
    $polski_faq_q_who = __('Kto może odstąpić od umowy w sklepie %s?', 'polski');
    /* translators: %d: withdrawal period in days (typically 14) */
    $polski_faq_a_when = __('Domyślnie masz %d dni od dnia otrzymania zamówienia, aby złożyć oświadczenie. Termin biegnie od dnia, w którym towar znalazł się w Twoim posiadaniu lub w posiadaniu wskazanej przez Ciebie osoby trzeciej innej niż przewoźnik.', 'polski');

    $polski_faq = [
        [
            'q' => sprintf($polski_faq_q_who, $polski_merchant),
            'a' => __('Każdy konsument, czyli osoba fizyczna kupująca w celach niezwiązanych z działalnością gospodarczą. W przypadku zakupu jako firma uprawnienie do odstąpienia bez podania przyczyny przysługuje tylko w ograniczonym zakresie.', 'polski'),
        ],
        [
            'q' => __('W jakim terminie mogę odstąpić od umowy?', 'polski'),
            'a' => sprintf($polski_faq_a_when, $polski_days),
        ],
        [
            'q' => __('Czy mogę zwrócić tylko niektóre produkty z zamówienia?', 'polski'),
            'a' => __('Tak. Po otwarciu formularza wybierzesz, których pozycji ma dotyczyć odstąpienie - możesz zwrócić wszystkie produkty, jeden produkt lub tylko wybraną liczbę sztuk. Pozostałe pozycje pozostaną w zamówieniu.', 'polski'),
        ],
        [
            'q' => __('Co się stanie po wysłaniu formularza?', 'polski'),
            'a' => __('Otrzymasz e-mail potwierdzający przyjęcie oświadczenia, zawierający unikalny numer deklaracji oraz pełne podsumowanie zamówienia. Następnie odeślij produkty na adres sklepu w terminie 14 dni od złożenia oświadczenia.', 'polski'),
        ],
        [
            'q' => __('Czy są produkty, których nie można zwrócić?', 'polski'),
            'a' => __('Tak. Zgodnie z art. 38 ustawy o prawach konsumenta z prawa odstąpienia wyłączone są m.in. produkty wykonane na zamówienie indywidualne, szybko psujące się, zapieczętowane ze względów higienicznych po otwarciu opakowania, oraz treści cyfrowe spełnione za wyraźną zgodą konsumenta przed upływem terminu.', 'polski'),
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
