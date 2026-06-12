<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Repository\WithdrawalRepository;
use Polski\Util\TemplateLoader;

/**
 * Guest withdrawal flow: a consumer who placed an order without registering can
 * still file a withdrawal by proving control of the billing email address.
 *
 * Two-step authentication:
 *   1. Visitor submits order number + billing email on the lookup page.
 *   2. A magic-link with a 32-byte token is emailed to the billing address.
 *   3. Clicking the link (same page, ?polski_wt=<token>) reveals the withdrawal form
 *      pre-bound to that order.
 *
 * The token is single-use, hashed at rest, and stored in a transient with a short TTL.
 * Rate limiting is enforced per IP + per order.
 */
final class GuestWithdrawalService implements HasHooks
{
    private const TOKEN_TTL_SECONDS = 1800;          // 30 min.
    private const RATE_LIMIT_WINDOW_SECONDS = 900;   // 15 min.
    private const RATE_LIMIT_MAX_REQUESTS = 5;
    private const TRANSIENT_PREFIX = 'polski_wt_';
    private const RATE_PREFIX = 'polski_wrl_';

    public function __construct(
        private readonly WithdrawalService $withdrawal,
        private readonly WithdrawalRepository $repository,
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('init', [$this, 'maybeHandleLookupRequest']);
        add_shortcode('polski_withdrawal_lookup', [$this, 'renderLookupShortcode']);
    }

    public function maybeHandleLookupRequest(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below before any side-effects.
        if (! isset($_POST['polski_withdrawal_lookup'])) {
            return;
        }

        $nonce = isset($_POST['polski_lookup_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['polski_lookup_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, 'polski_withdrawal_lookup')) {
            $this->setNotice('error', __('Security check failed. Please refresh the page and try again.', 'polski'));
            return;
        }

        $orderNumber = isset($_POST['polski_order_number'])
            ? sanitize_text_field(wp_unslash((string) $_POST['polski_order_number']))
            : '';
        $email = isset($_POST['polski_email'])
            ? sanitize_email(wp_unslash((string) $_POST['polski_email']))
            : '';

        if ($orderNumber === '' && $email === '') {
            $this->setNotice('error', __('Wpisz numer zamówienia (znajdziesz go w e-mailu potwierdzającym) i adres e-mail użyty przy zakupie.', 'polski'));
            return;
        }
        if ($orderNumber === '') {
            $this->setNotice('error', __('Brakuje numeru zamówienia. Sprawdź e-mail potwierdzający zakup i wpisz numer z linii „Twoje zamówienie #…".', 'polski'));
            return;
        }
        if ($email === '' || ! is_email($email)) {
            $this->setNotice('error', __('Adres e-mail wygląda na nieprawidłowy. Wpisz pełny adres w formacie ty@example.com - ten sam, który podałeś przy zakupie.', 'polski'));
            return;
        }

        if (! $this->checkRateLimit($email)) {
            $this->setNotice('error', __('Zbyt wiele prób w krótkim czasie. Spróbuj ponownie za 15 minut. Jeśli nie otrzymałeś wcześniej wysłanego linku, sprawdź folder Spam.', 'polski'));
            return;
        }

        $order = $this->locateOrder($orderNumber);
        $maskedNotice = __('Jeśli to zamówienie istnieje, wysłaliśmy link do formularza na adres e-mail podany przy zakupie. Sprawdź skrzynkę odbiorczą (oraz folder Spam) - wiadomość powinna dotrzeć w ciągu kilku minut.', 'polski');

        // Always show the same response so the form does not leak order-existence info.
        if (! $order instanceof \WC_Order || strcasecmp($order->get_billing_email(), $email) !== 0) {
            $this->setNotice('success', $maskedNotice);
            return;
        }

        if (! $this->withdrawal->isEligible($order)) {
            $this->setNotice('success', $maskedNotice);
            return;
        }

        $token = bin2hex(random_bytes(16));
        $payload = [
            'order_id' => $order->get_id(),
            'email' => $email,
            'created' => time(),
        ];

        set_transient(self::TRANSIENT_PREFIX . hash('sha256', $token), $payload, self::TOKEN_TTL_SECONDS);

        $this->sendMagicLink($order, $email, $token);
        $this->setNotice('success', $maskedNotice);
    }

    public function renderLookupShortcode(): string
    {
        // If a token is present, attempt to redeem it and render the actual withdrawal form.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Magic-link token from email; authenticity verified via the transient lookup below.
        $token = isset($_GET['polski_wt']) ? sanitize_text_field(wp_unslash((string) $_GET['polski_wt'])) : '';

        if ($token !== '') {
            $payload = $this->redeemToken($token);

            if ($payload !== null) {
                return $this->renderAuthorisedForm($payload, $token);
            }
        }

        $notice = $this->popNotice();

        ob_start();
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Template handles its own escaping.
        echo $this->templateLoader->render('forms/withdrawal-lookup', [
            'polski_nonce' => wp_create_nonce('polski_withdrawal_lookup'),
            'polski_notice' => $notice,
        ]);
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

        return (string) ob_get_clean();
    }

    /**
     * @param array{order_id: int, email: string, created: int} $payload
     */
    private function renderAuthorisedForm(array $payload, string $token): string
    {
        $order = wc_get_order($payload['order_id']);

        if (! $order instanceof \WC_Order) {
            return $this->renderError(__('This link is no longer valid.', 'polski'));
        }

        $requestMethod = isset($_SERVER['REQUEST_METHOD'])
            ? sanitize_key((string) wp_unslash($_SERVER['REQUEST_METHOD']))
            : '';

        if ($requestMethod === 'POST' && isset($_POST['polski_guest_submit'])) {
            $submitNonce = isset($_POST['polski_guest_nonce'])
                ? sanitize_text_field(wp_unslash((string) $_POST['polski_guest_nonce']))
                : '';

            if (! wp_verify_nonce($submitNonce, 'polski_guest_submit_' . $token)) {
                return $this->renderError(__('Weryfikacja bezpieczeństwa nie powiodła się. Załaduj stronę ponownie i spróbuj jeszcze raz.', 'polski'));
            }

            $reason = isset($_POST['polski_withdrawal_reason'])
                ? sanitize_textarea_field(wp_unslash((string) $_POST['polski_withdrawal_reason']))
                : null;

            $created = $this->repository->createForGuest(
                $order->get_id(),
                $payload['email'],
                $reason,
                null,
            );

            if ($created <= 0) {
                do_action(
                    'polski/withdrawal/persist_failed',
                    $order->get_id(),
                    ['flow' => 'guest', 'email' => $payload['email']],
                );

                return $this->renderError(__('Nie udało się zapisać oświadczenia. Spróbuj ponownie za chwilę albo skontaktuj się ze sklepem.', 'polski'));
            }

            $this->consumeToken($token);

            do_action('polski/withdrawal/guest_requested', $created, $order, $payload['email']);

            $declarationId = sprintf('POL-WD-%06d', $created);
            return '<div class="polski-withdrawal-success" role="status" aria-live="polite" lang="pl">'
                . '<h2>' . esc_html__('Oświadczenie złożone', 'polski') . '</h2>'
                . '<p>' . sprintf(
                    /* translators: %s = declaration id (POL-WD-XXXXXX) */
                    esc_html__('Twoje oświadczenie zostało zarejestrowane pod numerem %s. Wysłaliśmy potwierdzenie na adres podany przy zakupie - sprawdź skrzynkę odbiorczą oraz folder Spam.', 'polski'),
                    '<strong>' . esc_html($declarationId) . '</strong>',
                ) . '</p>'
                . '<p>' . esc_html__('Zachowaj numer oświadczenia na wypadek kontaktu ze sklepem.', 'polski') . '</p>'
                . '</div>';
        }

        ob_start();
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Template handles its own escaping.
        echo $this->templateLoader->render('forms/withdrawal-guest', [
            'polski_order' => $order,
            'polski_token' => $token,
            'polski_email' => $payload['email'],
            'polski_nonce' => wp_create_nonce('polski_guest_submit_' . $token),
        ]);
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

        return (string) ob_get_clean();
    }

    /**
     * @return array{order_id: int, email: string, created: int}|null
     */
    /**
     * REST-facing variant of the magic-link dispatch - shares the same rate
     * limiter, order lookup and e-mail body as the shortcode flow. The
     * masking step is the caller's responsibility (the REST controller
     * always returns the same response shape).
     */
    public function dispatchMagicLinkForRest(string $orderNumber, string $email): void
    {
        if (! $this->checkRateLimit($email)) {
            // Silent - caller will still return the masked notice.
            return;
        }

        $order = $this->locateOrder($orderNumber);
        if (! $order instanceof \WC_Order || strcasecmp($order->get_billing_email(), $email) !== 0) {
            return;
        }

        if (! $this->withdrawal->isEligible($order)) {
            return;
        }

        $token = bin2hex(random_bytes(16));
        $payload = [
            'order_id' => $order->get_id(),
            'email' => $email,
            'created' => time(),
        ];
        set_transient(self::TRANSIENT_PREFIX . hash('sha256', $token), $payload, self::TOKEN_TTL_SECONDS);

        $this->sendMagicLink($order, $email, $token);
    }

    /**
     * REST-facing token redeem. Mirrors the private redeemToken() so the
     * controller does not have to reach into private state.
     *
     * @return array{order_id: int, email: string, created: int}|null
     */
    public function redeemTokenForRest(string $token): ?array
    {
        return $this->redeemToken($token);
    }

    /**
     * REST-facing single-use token consumption.
     */
    public function consumeTokenForRest(string $token): void
    {
        $this->consumeToken($token);
    }

    /**
     * @return array{order_id: int, email: string, created: int}|null
     */
    private function redeemToken(string $token): ?array
    {
        $key = self::TRANSIENT_PREFIX . hash('sha256', $token);
        $payload = get_transient($key);

        if (! is_array($payload)) {
            return null;
        }

        if (! isset($payload['order_id'], $payload['email'], $payload['created'])) {
            return null;
        }

        return [
            'order_id' => (int) $payload['order_id'],
            'email' => (string) $payload['email'],
            'created' => (int) $payload['created'],
        ];
    }

    private function consumeToken(string $token): void
    {
        delete_transient(self::TRANSIENT_PREFIX . hash('sha256', $token));
    }

    private function locateOrder(string $orderNumber): ?\WC_Order
    {
        $orderNumber = ltrim($orderNumber, '#');
        $id = (int) $orderNumber;

        if ($id > 0) {
            $order = wc_get_order($id);
            if ($order instanceof \WC_Order) {
                return $order;
            }
        }

        $orders = wc_get_orders([
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => '_order_number',
                    'value' => $orderNumber,
                ],
            ],
        ]);

        if (is_array($orders) && isset($orders[0]) && $orders[0] instanceof \WC_Order) {
            return $orders[0];
        }

        return null;
    }

    /**
     * Enforce a per-IP attempt budget for guest withdrawal requests.
     *
     * The bucket is scoped exclusively by a salted SHA-256 hash of the client IP
     * so an attacker rotating through email addresses cannot escape rate limiting.
     * The function fails closed: if the transient store cannot record the new
     * count (storage broken, write rejected), the request is treated as rate
     * limited rather than letting the limiter become a silent no-op.
     *
     * The `$email` parameter is retained for caller compatibility but is no
     * longer part of the bucket key.
     */
    private function checkRateLimit(string $email): bool
    {
        unset($email); // intentionally unused.

        $key = self::RATE_PREFIX . hash('sha256', $this->clientIp() . wp_salt('auth'));
        $count = (int) get_transient($key);

        if ($count >= self::RATE_LIMIT_MAX_REQUESTS) {
            return false;
        }

        $stored = set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW_SECONDS);

        if ($stored === false) {
            // Fail closed: never let a broken transient backend turn the limiter
            // into a silent no-op.
            return false;
        }

        return true;
    }

    private function clientIp(): string
    {
        // Trust only REMOTE_ADDR unless an explicit reverse proxy is configured;
        // forwarding headers are spoofable and would defeat the rate limiter.
        return \Polski\Util\ClientIp::resolve();
    }

    private function sendMagicLink(\WC_Order $order, string $email, string $token): void
    {
        $pageUrl = $this->getLookupUrl();
        $link = add_query_arg('polski_wt', $token, $pageUrl);

        $subject = sprintf(
            /* translators: %s = order number */
            __('Link do odstąpienia od umowy - zamówienie #%s', 'polski'),
            $order->get_order_number(),
        );

        $body = sprintf(
            /* translators: 1: order number, 2: minutes until expiry, 3: magic link URL */
            __("Otrzymaliśmy prośbę o złożenie oświadczenia o odstąpieniu od umowy dla zamówienia #%1\$s.\n\nAby kontynuować, kliknij poniższy link w ciągu %2\$d minut:\n\n%3\$s\n\nLink jest jednorazowy. Jeśli to nie Ty wysłałeś prośbę, możesz zignorować tę wiadomość.", 'polski'),
            $order->get_order_number(),
            (int) round(self::TOKEN_TTL_SECONDS / 60),
            $link,
        );

        $sent = wp_mail($email, $subject, $body);

        if (! $sent) {
            do_action(
                'polski/withdrawal/mail_failed',
                $email,
                (string) $order->get_order_number(),
                ['order_id' => $order->get_id(), 'context' => 'magic_link'],
            );
        }
    }

    private function getLookupUrl(): string
    {
        $pageId = (int) ($this->withdrawalSettings()['lookup_page_id'] ?? 0);

        if ($pageId > 0) {
            $url = get_permalink($pageId);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return home_url('/');
    }

    /**
     * @return array<string, mixed>
     */
    private function withdrawalSettings(): array
    {
        $settings = get_option('polski_withdrawal', []);

        return is_array($settings) ? $settings : [];
    }

    private function setNotice(string $type, string $message): void
    {
        $key = 'polski_withdrawal_notice_' . $this->noticeKey();
        set_transient($key, ['type' => $type, 'message' => $message], 60);
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function popNotice(): ?array
    {
        $key = 'polski_withdrawal_notice_' . $this->noticeKey();
        $notice = get_transient($key);

        if (is_array($notice) && isset($notice['type'], $notice['message'])) {
            delete_transient($key);
            return [
                'type' => (string) $notice['type'],
                'message' => (string) $notice['message'],
            ];
        }

        return null;
    }

    private function noticeKey(): string
    {
        $cookieName = 'polski_wn';
        if (isset($_COOKIE[$cookieName])) {
            $value = sanitize_text_field(wp_unslash((string) $_COOKIE[$cookieName]));
            if ($value !== '' && ctype_alnum($value)) {
                return $value;
            }
        }

        $key = wp_generate_password(16, false, false);
        if (! headers_sent()) {
            $cookiePath = defined('COOKIEPATH') ? (string) COOKIEPATH : '';
            if ($cookiePath === '') {
                $cookiePath = '/';
            }
            $cookieDomain = defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '';
            setcookie($cookieName, $key, time() + 300, $cookiePath, $cookieDomain, is_ssl(), true);
        }

        return $key;
    }

    private function renderError(string $message): string
    {
        return '<div class="polski-withdrawal-error">' . esc_html($message) . '</div>';
    }
}
