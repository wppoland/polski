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
<section
    class="polski-withdrawal-lookup"
    aria-labelledby="polski-withdrawal-lookup-title"
    lang="pl"
    style="max-width: 65ch;"
>
    <h2 id="polski-withdrawal-lookup-title">
        <?php esc_html_e('Odstąpienie od umowy — formularz online', 'polski'); ?>
    </h2>

    <p class="polski-withdrawal-lookup__intro">
        <?php
        printf(
            /* translators: 1: merchant name, 2: number of days, 3: directive reference */
            esc_html__(
                'Jesteś konsumentem i kupiłeś u sprzedawcy %1$s? Masz prawo odstąpić od umowy zawartej na odległość bez podawania przyczyny w terminie %2$d dni od dnia, w którym otrzymałeś zamówienie. To uprawnienie wynika z art. 27 ustawy o prawach konsumenta wdrażającej dyrektywę %3$s. Aby złożyć oświadczenie, nie musisz logować się do konta w sklepie — wystarczy, że poniżej podasz adres e-mail użyty przy zakupie oraz numer zamówienia. Po weryfikacji wyślemy na ten adres jednorazowy link, który otworzy formularz odstąpienia. Link jest ważny przez 30 minut i można go użyć tylko raz, dzięki czemu Twoje oświadczenie pozostaje bezpieczne, a my zachowujemy dowód jego złożenia na trwałym nośniku. W kolejnym kroku wybierzesz, czy chcesz zwrócić całe zamówienie, czy tylko wybrane pozycje (możliwe są też zwroty częściowe, np. jednej z kilku sztuk). Po przesłaniu oświadczenia otrzymasz e-mail z potwierdzeniem zawierającym numer deklaracji, datę złożenia oraz pełne podsumowanie zamówienia.',
                'polski',
            ),
            esc_html($polski_merchant),
            (int) $polski_days,
            '2011/83/UE (zmienionej przez 2023/2673)',
        );
        ?>
    </p>

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

    <form method="post" action="" novalidate aria-describedby="polski-withdrawal-lookup-help">
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
</section>

<?php
// FAQPage schema — surfaced once per page render, helps search engines and AI
// assistants answer common withdrawal questions about this store. Cached as a
// static guard to avoid double-printing if the shortcode appears twice.
if (! isset($GLOBALS['polski_withdrawal_lookup_schema_emitted'])) {
    $GLOBALS['polski_withdrawal_lookup_schema_emitted'] = true;

    $polski_faq = [
        [
            'q' => sprintf(__('Kto może odstąpić od umowy w sklepie %s?', 'polski'), $polski_merchant),
            'a' => __('Każdy konsument, czyli osoba fizyczna kupująca w celach niezwiązanych z działalnością gospodarczą. W przypadku zakupu jako firma uprawnienie do odstąpienia bez podania przyczyny przysługuje tylko w ograniczonym zakresie.', 'polski'),
        ],
        [
            'q' => __('W jakim terminie mogę odstąpić od umowy?', 'polski'),
            'a' => sprintf(__('Domyślnie masz %d dni od dnia otrzymania zamówienia, aby złożyć oświadczenie. Termin biegnie od dnia, w którym towar znalazł się w Twoim posiadaniu lub w posiadaniu wskazanej przez Ciebie osoby trzeciej innej niż przewoźnik.', 'polski'), $polski_days),
        ],
        [
            'q' => __('Czy mogę zwrócić tylko niektóre produkty z zamówienia?', 'polski'),
            'a' => __('Tak. Po otwarciu formularza wybierzesz, których pozycji ma dotyczyć odstąpienie — możesz zwrócić wszystkie produkty, jeden produkt lub tylko wybraną liczbę sztuk. Pozostałe pozycje pozostaną w zamówieniu.', 'polski'),
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
