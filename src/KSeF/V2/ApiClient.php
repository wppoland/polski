<?php

declare(strict_types=1);

namespace Polski\KSeF\V2;

defined('ABSPATH') || exit;

/**
 * HTTP client for the KSeF 2.0 API, authenticated with a KSeF certificate.
 *
 * Contract from the Ministry's OpenAPI document at
 * https://api-test.ksef.mf.gov.pl/docs/v2/openapi.json. Authentication is the
 * XAdES chain: POST /auth/challenge, POST /auth/xades-signature with the signed
 * AuthTokenRequest, GET /auth/{ref} until it settles, POST /auth/token/redeem.
 * That yields an access token (15 minutes) and a refresh token (7 days), so the
 * pair is cached and the access token refreshed rather than signing a new
 * challenge for every invoice.
 *
 * Every failure is a RuntimeException carrying the HTTP status as its code (0
 * when KSeF was not reached) and the Ministry's own wording, because that
 * message is what the merchant ends up reading on the invoice. HTTP 429 is the
 * subclass KSeFRateLimited, whose retryAfter says when asking again is allowed.
 */
final class ApiClient
{
    /**
     * The session the last sendInvoice() opened, kept when it throws, so a
     * caller can recognise its own document if a retry answers 440.
     */
    public string $lastSession = '';

    public const BASE_URLS = [
        'test' => 'https://api-test.ksef.mf.gov.pl/v2',
        'production' => 'https://api.ksef.mf.gov.pl/v2',
    ];

    /** Sealed JSON with both tokens, scoped to environment, NIP and certificate. */
    public const TOKEN_OPTION = 'polski_pro_ksef_tokens';

    private const KEYS_TRANSIENT = 'polski_pro_ksef_public_keys_';

    /** A token this close to expiry is treated as expired, so it cannot die mid-request. */
    private const EXPIRY_MARGIN = 60;

    private const AUTH_POLLS = 10;
    private const STATUS_POLLS = 8;
    private const POLL_SECONDS = 2;

    /** Error code for a publicKeyId KSeF no longer accepts (key rotated or withdrawn). */
    private const KEY_REJECTED = 21470;

    private readonly string $environment;

    /** @var \Closure(string, string, array<string, string>, string|null): array{0: int, 1: string, 2?: array<string, string>} */
    private readonly \Closure $transport;

    /** @var \Closure(int): void */
    private readonly \Closure $sleep;

    /**
     * @param string $privateKeyPem Unencrypted PEM, as KSeFCredentials::load() returns it.
     * @param (\Closure(string, string, array<string, string>, string|null): array{0: int, 1: string, 2?: array<string, string>})|null $transport
     *        Method, URL, headers, body; returns status, body and response headers
     *        with lowercase names (optional). Defaults to wp_remote_request.
     * @param (\Closure(int): void)|null $sleep Injectable so tests do not wait on polls.
     */
    public function __construct(
        string $environment,
        private readonly string $nip,
        private readonly string $certificatePem,
        private readonly string $privateKeyPem,
        ?\Closure $transport = null,
        ?\Closure $sleep = null,
    ) {
        // Only the exact string opts in to production, the rule Migration_2_8_0
        // relies on: an empty or unexpected value must never file for real.
        $this->environment = $environment === 'production' ? 'production' : 'test';
        $this->transport = $transport ?? self::wpTransport(...);
        $this->sleep = $sleep ?? static function (int $seconds): void {
            sleep($seconds);
        };
    }

    /** 'test' or 'production', already normalised: pass this on, never the raw setting. */
    public function environment(): string
    {
        return $this->environment;
    }

    /** Null when no usable certificate and key are stored. */
    public static function fromSettings(string $nip): ?self
    {
        $credentials = KSeFCredentials::load();

        if ($credentials === null) {
            return null;
        }

        $settings = get_option('polski_pro_ksef', []);
        $environment = is_array($settings) ? (string) ($settings['environment'] ?? 'test') : 'test';

        return new self($environment, $nip, $credentials['certificate'], $credentials['private_key']);
    }

    /**
     * A valid access token: cached, refreshed, or from a fresh authentication.
     */
    public function accessToken(): string
    {
        $tokens = $this->storedTokens();
        $now = time();

        if ($tokens !== null && $tokens['access_until'] - self::EXPIRY_MARGIN > $now) {
            return $tokens['access'];
        }

        if ($tokens !== null && $tokens['refresh_until'] - self::EXPIRY_MARGIN > $now) {
            try {
                $access = $this->json('POST', '/auth/token/refresh', null, $tokens['refresh'])['accessToken'] ?? null;
                $access = is_array($access) ? $access : [];
                $token = (string) ($access['token'] ?? '');

                if ($token !== '') {
                    $this->storeTokens($token, self::until($access), $tokens['refresh'], $tokens['refresh_until']);

                    return $token;
                }
            } catch (KSeFRateLimited $limited) {
                // Authenticating afresh would only be one more request inside the block.
                throw $limited;
            } catch (\RuntimeException) {
                // A refused refresh token is not fatal: the certificate can
                // always authenticate from scratch, which is what follows.
            }
        }

        return $this->authenticate();
    }

    /**
     * Opens an online session, sends one document, waits briefly for its
     * verdict and closes the session again.
     *
     * A document still pending after the wait comes back with a pending status;
     * the caller asks again later with invoiceStatus(). The session is closed
     * either way, which is also what makes KSeF generate the UPO.
     *
     * @param bool        $offlineMode            Declares the document an offline invoice (tryby-offline.md).
     * @param string|null $hashOfCorrectedInvoice Plain Base64 SHA-256 of the rejected offline file, for a technical correction.
     * @return array{session: string, invoice: string, status: array<string, mixed>}
     */
    public function sendInvoice(string $xml, bool $offlineMode = false, ?string $hashOfCorrectedInvoice = null): array
    {
        $this->lastSession = '';
        $material = KSeFCrypto::newSymmetricKey();
        [$code, $raw] = $this->openOnline($xml, $material);

        // ksef-docs bezpieczenstwo/klucze-publiczne-do-szyfrowania.md 4.4: the
        // cached key was rotated away, so fetch the list again and retry once.
        if ($code === 400 && self::hasErrorCode($raw, self::KEY_REJECTED)) {
            delete_transient(self::KEYS_TRANSIENT . $this->environment);
            [$code, $raw] = $this->openOnline($xml, $material);
        }

        $opened = $this->decode($code, $raw, '/sessions/online');
        $session = (string) ($opened['referenceNumber'] ?? '');

        if ($session === '') {
            throw new \RuntimeException(__('KSeF did not open a session.', 'polski')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- stored raw in ksef_error, escaped wherever it is rendered, returned raw over REST.
        }

        $this->lastSession = $session;

        $invoice = '';
        $status = [];

        try {
            $encrypted = KSeFCrypto::encryptInvoice($xml, $material['key'], $material['iv']);
            $sent = $this->authed(
                'POST',
                '/sessions/online/' . rawurlencode($session) . '/invoices',
                SessionRequest::sendInvoice($xml, $encrypted, $offlineMode, $hashOfCorrectedInvoice),
            );
            $invoice = (string) ($sent['referenceNumber'] ?? '');

            if ($invoice === '') {
                throw new \RuntimeException(__('KSeF did not accept the invoice into the session.', 'polski'));
            }

            // From here the document is in KSeF, so a failed poll must not turn
            // into an exception: that would lose the reference and invite a
            // second submission. Return what is known and let the caller poll.
            try {
                for ($i = 0; $i < self::STATUS_POLLS; $i++) {
                    ($this->sleep)(self::POLL_SECONDS);
                    $status = $this->invoiceStatus($session, $invoice);

                    if (self::statusCode($status) >= 200) {
                        break;
                    }
                }
            } catch (\RuntimeException) {
                $status = [];
            }
        } finally {
            $this->closeSession($session);
        }

        return ['session' => $session, 'invoice' => $invoice, 'status' => $status];
    }

    /**
     * POST /sessions/online with the session key wrapped for the current
     * SymmetricKeyEncryption certificate.
     *
     * @param array{key: string, iv: string} $material
     * @return array{0: int, 1: string}
     */
    private function openOnline(string $xml, array $material): array
    {
        $publicKey = $this->publicKey('SymmetricKeyEncryption');

        // TEST skips NIP checksum validation unless asked; ask, so a document
        // that production would refuse is refused on TEST too.
        $headers = $this->environment === 'test' ? ['X-KSeF-Feature' => 'subject-identifier-validation'] : [];

        return $this->authedRaw('POST', '/sessions/online', SessionRequest::openOnline(
            KSeFCrypto::encryptSymmetricKey($material['key'], $publicKey['pem']),
            $material['iv'],
            $publicKey['id'],
            self::formCode($xml),
        ), $headers);
    }

    /**
     * GET /sessions/{ref}/invoices/{invoiceRef}: ksefNumber once numbered, and
     * status.code 100/150 pending, 200 accepted, 4xx/5xx refused.
     *
     * @return array<string, mixed>
     */
    public function invoiceStatus(string $session, string $invoice): array
    {
        return $this->authed('GET', '/sessions/' . rawurlencode($session) . '/invoices/' . rawurlencode($invoice));
    }

    /**
     * The UPO XML for one numbered invoice. KSeF only produces it after the
     * session is closed, so right after sending this may still fail.
     */
    public function upo(string $session, string $ksefNumber): string
    {
        $path = '/sessions/' . rawurlencode($session) . '/invoices/ksef/' . rawurlencode($ksefNumber) . '/upo';
        [$code, $body] = $this->authedRaw('GET', $path, null);

        if ($code !== 200 || ! str_contains($body, '<')) {
            $this->decode($code === 200 ? 502 : $code, $body, $path);
        }

        return $body;
    }

    /** @param array<string, mixed> $status */
    public static function statusCode(array $status): int
    {
        $info = $status['status'] ?? null;

        return is_array($info) ? (int) ($info['code'] ?? 100) : 100;
    }

    /**
     * status.description plus its details, the way KSeF explains a refusal.
     *
     * @param array<string, mixed> $status
     */
    public static function statusText(array $status): string
    {
        $info = is_array($status['status'] ?? null) ? $status['status'] : [];
        $details = is_array($info['details'] ?? null) ? $info['details'] : [];
        $text = trim((string) ($info['description'] ?? '') . ' ' . implode(' ', array_map('strval', $details)));

        return $text !== '' ? $text : (string) wp_json_encode($status);
    }

    private function authenticate(): string
    {
        $challenge = $this->json('POST', '/auth/challenge', []);

        $signed = XadesSigner::sign(
            AuthTokenRequest::build((string) ($challenge['challenge'] ?? ''), $this->nip),
            $this->privateKeyPem,
            $this->certificatePem,
        );

        [$code, $body] = $this->call('POST', '/auth/xades-signature', $signed, '', 'application/xml');
        $init = $this->decode($code, $body, '/auth/xades-signature');
        $reference = (string) ($init['referenceNumber'] ?? '');
        $authToken = (string) ($init['authenticationToken']['token'] ?? '');

        if ($reference === '' || $authToken === '') {
            throw new \RuntimeException(__('KSeF did not start the authentication.', 'polski')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- stored raw in ksef_error, escaped wherever it is rendered, returned raw over REST.
        }

        for ($i = 0;; $i++) {
            $state = $this->json('GET', '/auth/' . rawurlencode($reference), null, $authToken);
            $stateCode = self::statusCode($state);

            if ($stateCode === 200) {
                break;
            }

            if ($stateCode !== 100) {
                $message = sprintf(
                    /* translators: %s: the reason KSeF gives, in its own words. */
                    __('KSeF refused the certificate: %s', 'polski'),
                    self::statusText($state),
                );
                throw new \RuntimeException($message); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- stored raw in ksef_error, escaped wherever it is rendered, returned raw over REST.
            }

            if ($i >= self::AUTH_POLLS) {
                throw new \RuntimeException(__('KSeF did not finish the authentication in time.', 'polski')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- stored raw in ksef_error, escaped wherever it is rendered, returned raw over REST.
            }

            ($this->sleep)(1);
        }

        // Tokens can be redeemed only once, so store them before anything else can fail.
        $tokens = $this->json('POST', '/auth/token/redeem', null, $authToken);
        $access = is_array($tokens['accessToken'] ?? null) ? $tokens['accessToken'] : [];
        $refresh = is_array($tokens['refreshToken'] ?? null) ? $tokens['refreshToken'] : [];
        $token = (string) ($access['token'] ?? '');

        if ($token === '') {
            throw new \RuntimeException(__('KSeF did not issue an access token.', 'polski')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- stored raw in ksef_error, escaped wherever it is rendered, returned raw over REST.
        }

        $this->storeTokens($token, self::until($access), (string) ($refresh['token'] ?? ''), self::until($refresh));

        return $token;
    }

    /**
     * The Ministry's current certificate for one usage, cached for a day.
     *
     * Per ksef-docs bezpieczenstwo/klucze-publiczne-do-szyfrowania.md 4.1: the
     * right usage, valid now (validFrom <= now < validTo), and among several
     * the latest validFrom, which is the one a rotation just introduced.
     *
     * @return array{pem: string, id: string}
     */
    private function publicKey(string $usage): array
    {
        $transient = self::KEYS_TRANSIENT . $this->environment;
        $certificates = get_transient($transient);

        if (! is_array($certificates) || $certificates === []) {
            $certificates = $this->json('GET', '/security/public-key-certificates');
            set_transient($transient, $certificates, DAY_IN_SECONDS);
        }

        $now = time();
        $best = null;
        $bestFrom = 0;

        foreach ($certificates as $certificate) {
            if (! is_array($certificate) || ! in_array($usage, (array) ($certificate['usage'] ?? []), true)) {
                continue;
            }

            $from = (int) strtotime((string) ($certificate['validFrom'] ?? ''));

            if ($from <= $now && (int) strtotime((string) ($certificate['validTo'] ?? '')) > $now && ($best === null || $from > $bestFrom)) {
                $best = $certificate;
                $bestFrom = $from;
            }
        }

        if ($best !== null) {
            return [
                'pem' => KSeFCrypto::toPem((string) ($best['certificate'] ?? '')),
                'id' => (string) ($best['publicKeyId'] ?? ''),
            ];
        }

        // Probably a stale cache across a key rotation; fetch afresh next time.
        delete_transient($transient);

        $message = sprintf(
            /* translators: %s: certificate usage name from the KSeF API. */
            __('KSeF offered no current %s certificate.', 'polski'),
            $usage,
        );
        throw new \RuntimeException($message); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- stored raw in ksef_error, escaped wherever it is rendered, returned raw over REST.
    }

    private function closeSession(string $session): void
    {
        try {
            $this->authedRaw('POST', '/sessions/online/' . rawurlencode($session) . '/close', null);
        } catch (\RuntimeException) {
            // KSeF closes an abandoned session itself when it expires.
        }
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function authed(string $method, string $path, ?array $body = null): array
    {
        [$code, $raw] = $this->authedRaw($method, $path, $body);

        return $this->decode($code, $raw, $path);
    }

    /**
     * A call with the access token, retried once from scratch on 401: the
     * Ministry can revoke a token before its stated expiry.
     *
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers Extra request headers.
     * @return array{0: int, 1: string}
     */
    private function authedRaw(string $method, string $path, ?array $body, array $headers = []): array
    {
        $encoded = $body === null ? null : (string) wp_json_encode($body);
        $response = $this->call($method, $path, $encoded, $this->accessToken(), 'application/json', $headers);

        if ($response[0] === 401) {
            delete_option(self::TOKEN_OPTION);
            $response = $this->call($method, $path, $encoded, $this->accessToken(), 'application/json', $headers);
        }

        return $response;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function json(string $method, string $path, ?array $body = null, string $bearer = ''): array
    {
        // The API wants an object where there is nothing to say; [] would encode as a list.
        $encoded = $body === null ? null : ($body === [] ? '{}' : (string) wp_json_encode($body));
        [$code, $raw] = $this->call($method, $path, $encoded, $bearer);

        return $this->decode($code, $raw, $path);
    }

    /**
     * One request. HTTP 429 never comes back as a response: it is thrown as
     * KSeFRateLimited so no caller can miss the Retry-After it carries.
     *
     * @param array<string, string> $extra Extra request headers.
     * @return array{0: int, 1: string}
     */
    private function call(
        string $method,
        string $path,
        ?string $body,
        string $bearer = '',
        string $contentType = 'application/json',
        array $extra = [],
    ): array {
        $headers = [
            'Content-Type' => $contentType,
            'X-Error-Format' => 'problem-details',
        ] + $extra;

        if ($bearer !== '') {
            $headers['Authorization'] = 'Bearer ' . $bearer;
        }

        $response = ($this->transport)($method, self::BASE_URLS[$this->environment] . $path, $headers, $body);
        $code = $response[0];
        $raw = $response[1];

        if ($code === 429) {
            $data = json_decode($raw, true);
            $retryAfter = KSeFRateLimited::seconds((string) (($response[2] ?? [])['retry-after'] ?? ''));

            $message = sprintf(
                /* translators: 1: API path, 2: seconds to wait, 3: the error KSeF returned. */
                __('KSeF rate limit hit on %1$s, retry after %2$d s: %3$s', 'polski'),
                $path,
                $retryAfter,
                self::problem(is_array($data) ? $data : [], $raw),
            );
            throw new KSeFRateLimited($message, $retryAfter); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- stored raw in ksef_error, escaped wherever it is rendered, returned raw over REST.
        }

        return [$code, $raw];
    }

    /** Whether a problem-details (or legacy exception) body lists this error code. */
    private static function hasErrorCode(string $raw, int $code): bool
    {
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return false;
        }

        $entries = array_merge(
            is_array($data['errors'] ?? null) ? $data['errors'] : [],
            is_array($data['exception']['exceptionDetailList'] ?? null) ? $data['exception']['exceptionDetailList'] : [],
        );

        foreach ($entries as $entry) {
            if (is_array($entry) && (int) ($entry['code'] ?? $entry['exceptionCode'] ?? 0) === $code) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function decode(int $code, string $body, string $path): array
    {
        $data = json_decode($body, true);
        $data = is_array($data) ? $data : [];

        if ($code < 200 || $code >= 300) {
            $message = sprintf(
                /* translators: 1: HTTP status code, 2: API path, 3: the error KSeF returned. */
                __('KSeF answered HTTP %1$d on %2$s: %3$s', 'polski'),
                $code,
                $path,
                self::problem($data, $body),
            );
            throw new \RuntimeException($message, $code); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- stored raw in ksef_error, escaped wherever it is rendered, returned raw over REST.
        }

        return $data;
    }

    /**
     * Both error shapes the API documents: problem details (asked for with
     * X-Error-Format) and the older, deprecated exception envelope.
     *
     * @param array<string, mixed> $data
     */
    private static function problem(array $data, string $raw): string
    {
        $parts = [(string) ($data['detail'] ?? $data['title'] ?? '')];
        $entries = array_merge(
            is_array($data['errors'] ?? null) ? $data['errors'] : [],
            is_array($data['exception']['exceptionDetailList'] ?? null) ? $data['exception']['exceptionDetailList'] : [],
        );

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $parts[] = (string) ($entry['description'] ?? $entry['exceptionDescription'] ?? '');

            foreach ((array) ($entry['details'] ?? []) as $detail) {
                $parts[] = (string) $detail;
            }
        }

        $text = trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));

        return $text !== '' ? $text : substr(trim(wp_strip_all_tags($raw)), 0, 300);
    }

    /**
     * The session must be opened for the form the document declares, so read
     * it from the document rather than assume it. FaMapper writes
     * KodFormularza kodSystemowy="FA (3)"; SessionRequest keys it "FA(3)".
     */
    private static function formCode(string $xml): string
    {
        if (preg_match('/kodSystemowy="([^"]+)"/', $xml, $match) === 1) {
            $code = str_replace(' ', '', $match[1]);

            if (isset(SessionRequest::FORM_CODES[$code])) {
                return $code;
            }
        }

        throw new \InvalidArgumentException('The document names no KSeF form a session can be opened for.');
    }

    /**
     * @return array{access: string, access_until: int, refresh: string, refresh_until: int}|null
     */
    private function storedTokens(): ?array
    {
        $plain = KSeFCredentials::open((string) get_option(self::TOKEN_OPTION, ''));
        $tokens = $plain === null ? null : json_decode($plain, true);

        if (! is_array($tokens) || ($tokens['scope'] ?? '') !== $this->scope()) {
            return null;
        }

        return [
            'access' => (string) ($tokens['access'] ?? ''),
            'access_until' => (int) ($tokens['access_until'] ?? 0),
            'refresh' => (string) ($tokens['refresh'] ?? ''),
            'refresh_until' => (int) ($tokens['refresh_until'] ?? 0),
        ];
    }

    private function storeTokens(string $access, int $accessUntil, string $refresh, int $refreshUntil): void
    {
        update_option(self::TOKEN_OPTION, KSeFCredentials::seal((string) wp_json_encode([
            'scope' => $this->scope(),
            'access' => $access,
            'access_until' => $accessUntil,
            'refresh' => $refresh,
            'refresh_until' => $refreshUntil,
        ])), false);
    }

    /** Tokens belong to one identity; a new certificate, NIP or environment must not reuse them. */
    private function scope(): string
    {
        return hash('sha256', $this->environment . '|' . $this->nip . '|' . $this->certificatePem);
    }

    /**
     * validUntil as a timestamp. A missing or unreadable value counts as
     * already expired, which costs a refresh rather than a failed request.
     *
     * @param array<string, mixed> $token
     */
    private static function until(array $token): int
    {
        return (int) strtotime((string) ($token['validUntil'] ?? '')) ?: time();
    }

    /**
     * @param array<string, string> $headers
     * @return array{0: int, 1: string}
     */
    private static function wpTransport(string $method, string $url, array $headers, ?string $body): array
    {
        $args = ['method' => $method, 'timeout' => 30, 'headers' => $headers];

        if ($body !== null) {
            $args['body'] = $body;
        }

        $response = wp_remote_request($url, $args);

        // No answer at all carries code 503, like an answer saying KSeF is
        // down, so callers can tell an outage from a refusal by the code.
        if (is_wp_error($response)) {
            $message = sprintf(
                /* translators: %s: the transport error message. */
                __('KSeF could not be reached: %s', 'polski'),
                $response->get_error_message(),
            );
            throw new \RuntimeException($message, 503); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- stored raw in ksef_error, escaped wherever it is rendered, returned raw over REST.
        }

        $headers = [];

        foreach (wp_remote_retrieve_headers($response) as $name => $value) {
            $headers[strtolower((string) $name)] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return [(int) wp_remote_retrieve_response_code($response), (string) wp_remote_retrieve_body($response), $headers];
    }
}

/**
 * HTTP 429 from KSeF. retryAfter is the Retry-After header in seconds (60 when
 * KSeF sent none): reschedule no earlier than that, since asking again inside
 * the block makes KSeF extend it.
 */
final class KSeFRateLimited extends \RuntimeException
{
    private const DEFAULT_SECONDS = 60;

    public function __construct(string $message, public readonly int $retryAfter)
    {
        parent::__construct($message, 429);
    }

    /** Retry-After as seconds: delta-seconds or an HTTP date, at least 1. */
    public static function seconds(string $header): int
    {
        $header = trim($header);

        if ($header === '') {
            return self::DEFAULT_SECONDS;
        }

        if (ctype_digit($header)) {
            return max(1, (int) $header);
        }

        $at = strtotime($header);

        return $at === false ? self::DEFAULT_SECONDS : max(1, $at - time());
    }
}
