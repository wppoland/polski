<?php

declare(strict_types=1);

/**
 * The KSeF 2.0 production path against a fake Ministry: token lifecycle,
 * sealed credentials, and what KSeFSyncService records on the invoice.
 *
 *   php tests/ksef-api-client-test.php
 *
 * The fake transport answers like api-test.ksef.mf.gov.pl/v2 does and goes one
 * step further than a stub: it unwraps the session key with its own private
 * key and decrypts the invoice, so a send only passes if the Ministry could
 * have read the document. The live proof is tools/ksef-client-e2e.php.
 */

define('ABSPATH', __DIR__);
const DAY_IN_SECONDS = 86400;

$root = dirname(__DIR__) . '/';

require $root . 'vendor/phpseclib/phpseclib/phpseclib/bootstrap.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (! str_starts_with($class, 'phpseclib3\\')) {
        return;
    }
    $path = $root . 'vendor/phpseclib/phpseclib/phpseclib/'
        . str_replace('\\', '/', substr($class, strlen('phpseclib3\\'))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
foreach (['XMLSecurityDSig', 'XMLSecurityKey', 'XMLSecEnc'] as $class) {
    require $root . "vendor/robrichards/xmlseclibs/src/{$class}.php";
}
require $root . 'vendor/robrichards/xmlseclibs/src/Utils/XPath.php';

// --- WordPress, as little of it as the code under test touches ---------------

$GLOBALS['options'] = [];
$GLOBALS['transients'] = [];
$GLOBALS['scheduled'] = [];
$GLOBALS['actions'] = [];
$GLOBALS['salt'] = 'salt-one';

function __(string $text, string $domain = ''): string
{
    return $text;
}
function esc_html(string $text): string
{
    return $text;
}
function wp_json_encode(mixed $data): string|false
{
    return json_encode($data);
}
function wp_strip_all_tags(string $text): string
{
    return strip_tags($text);
}
function wp_salt(string $scheme = 'auth'): string
{
    return $GLOBALS['salt'] . $scheme;
}
function get_option(string $name, mixed $default = false): mixed
{
    return $GLOBALS['options'][$name] ?? $default;
}
function update_option(string $name, mixed $value, mixed $autoload = null): bool
{
    $GLOBALS['options'][$name] = $value;
    return true;
}
function delete_option(string $name): bool
{
    unset($GLOBALS['options'][$name]);
    return true;
}
function get_transient(string $name): mixed
{
    return $GLOBALS['transients'][$name] ?? false;
}
function set_transient(string $name, mixed $value, int $ttl = 0): bool
{
    $GLOBALS['transients'][$name] = $value;
    return true;
}
function delete_transient(string $name): bool
{
    unset($GLOBALS['transients'][$name]);
    return true;
}
function get_bloginfo(string $show = ''): string
{
    return 'Sklep';
}
function wc_get_order(int $id): mixed
{
    return false;
}
function add_action(string $hook, mixed $callback, int $priority = 10, int $args = 1): void
{
}
function do_action(string $hook, mixed ...$args): void
{
    $GLOBALS['actions'][] = [$hook, ...$args];
}
function as_schedule_single_action(int $timestamp, string $hook, array $args = [], string $group = ''): int
{
    $GLOBALS['scheduled'][] = [$hook, $args, $timestamp - time()];
    return 1;
}

eval('namespace Polski\Contract; interface HasHooks { public function registerHooks(): void; }');

/** The invoices table, one row at a time. */
final class wpdb
{
    public string $prefix = 'wp_';
    /** @var array<int, object> */
    public array $rows = [];
    /** @var array<int, list<object>> */
    public array $items = [];

    /** @return array{0: string, 1: array<int, mixed>} */
    public function prepare(string $query, mixed ...$args): array
    {
        return [$query, $args];
    }

    public function get_row(array $prepared): ?object
    {
        return $this->rows[$prepared[1][1]] ?? null;
    }

    /** @return list<object> */
    public function get_results(array $prepared): array
    {
        return $this->items[$prepared[1][1]] ?? [];
    }

    public function get_var(array $prepared): mixed
    {
        return $this->rows[$prepared[1][1]]->ksef_upo ?? null;
    }

    /** @param array<string, mixed> $data @param array{id: int} $where */
    public function update(string $table, array $data, array $where): int
    {
        foreach ($data as $column => $value) {
            $this->rows[$where['id']]->{$column} = $value;
        }
        return 1;
    }
}

foreach (['InvoiceType', 'InvoiceStatus', 'InvoiceItem', 'Invoice'] as $class) {
    require $root . "src/KSeF/Model/{$class}.php";
}
require $root . 'src/KSeF/Repository/InvoiceRepository.php';
foreach (['KSeFCrypto', 'KSeFCredentials', 'AuthTokenRequest', 'XadesSigner', 'SessionRequest', 'FaParty', 'VerificationLink', 'FaMapper', 'FaValidator', 'ApiClient'] as $class) {
    require $root . "src/KSeF/V2/{$class}.php";
}
require $root . 'src/KSeF/KSeFSyncService.php';

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Polski\KSeF\KSeFSyncService;
use Polski\KSeF\V2\ApiClient;
use Polski\KSeF\V2\FaMapper;
use Polski\KSeF\V2\FaValidator;
use Polski\KSeF\V2\KSeFCredentials;
use Polski\KSeF\V2\KSeFRateLimited;
use Polski\KSeF\Repository\InvoiceRepository;

$failures = 0;
$checks = 0;

function check(string $label, bool $ok): void
{
    global $failures, $checks;
    $checks++;
    echo ($ok ? 'ok    ' : 'FAIL  ') . $label . "\n";
    if (! $ok) {
        $failures++;
    }
}

/** @return array{0: string, 1: string} Key PEM, certificate PEM. */
function selfSigned(string $commonName, string $passphrase = ''): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => $commonName, 'countryName' => 'PL'], $key, ['digest_alg' => 'sha256']);
    $cert = openssl_csr_sign($csr, null, $key, 30, ['digest_alg' => 'sha256']);
    openssl_x509_export($cert, $certPem);
    openssl_pkey_export($key, $keyPem, $passphrase !== '' ? $passphrase : null);

    return [$keyPem, $certPem];
}

function iso(int $offset): string
{
    return gmdate('Y-m-d\TH:i:s\Z', time() + $offset);
}

/**
 * Routes by method and path pattern; a route holding several answers hands
 * them out in turn and then keeps repeating the last one.
 */
final class FakeKsef
{
    /** @var list<array{0: string, 1: string, 2: array<string, string>, 3: ?string}> */
    public array $log = [];
    /** @var array<string, list<mixed>> */
    public array $routes = [];

    public function on(string $method, string $pattern, mixed ...$answers): void
    {
        $this->routes["{$method} {$pattern}"] = $answers;
    }

    /** @param array<string, string> $headers @return array{0: int, 1: string} */
    public function __invoke(string $method, string $url, array $headers, ?string $body): array
    {
        $path = (string) preg_replace('#^https://api(-test)?\.ksef\.mf\.gov\.pl/v2#', '', $url);
        $this->log[] = [$method, $path, $headers, $body];

        foreach ($this->routes as $key => $answers) {
            [$routeMethod, $pattern] = explode(' ', $key, 2);
            if ($routeMethod !== $method || preg_match('#^' . $pattern . '$#', $path) !== 1) {
                continue;
            }
            $answer = count($answers) > 1 ? array_shift($this->routes[$key]) : $answers[0];

            return is_callable($answer) ? $answer($body, $headers) : $answer;
        }

        return [404, '{"title":"Not Found","detail":"not routed: ' . $method . ' ' . $path . '"}'];
    }

    /** @return list<string> */
    public function calls(int $from = 0): array
    {
        return array_map(static fn (array $call): string => $call[0] . ' ' . $call[1], array_slice($this->log, $from));
    }

    public function authorisation(int $index): string
    {
        return $this->log[$index][2]['Authorization'] ?? '';
    }
}

function tokens(): array
{
    return json_decode((string) KSeFCredentials::open((string) get_option(ApiClient::TOKEN_OPTION, '')), true) ?? [];
}

function rewriteTokens(array $changes): void
{
    update_option(ApiClient::TOKEN_OPTION, KSeFCredentials::seal((string) json_encode(array_merge(tokens(), $changes))));
}

$nip = '9999999999';
[$shopKey, $shopCert] = selfSigned('Sklep Testowy');
[$ministryKey, $ministryCert] = selfSigned('Ministerstwo Finansow');
$ministryDer = preg_replace('/-----[A-Z ]+-----|\s/', '', $ministryCert);
$ministryPrivate = PublicKeyLoader::loadPrivateKey($ministryKey)
    ->withPadding(RSA::ENCRYPTION_OAEP)->withHash('sha256')->withMGFHash('sha256');

$fake = new FakeKsef();
$challenge = '20260926-CR-ABCDEF0123-456789ABCD-EF';
$fake->on('POST', '/auth/challenge', [200, json_encode(['challenge' => $challenge, 'timestamp' => iso(0), 'timestampMs' => time() * 1000])]);
$fake->on('POST', '/auth/xades-signature', [202, json_encode([
    'referenceNumber' => '20260926-AU-0000000001-0000000001-01',
    'authenticationToken' => ['token' => 'operation-token', 'validUntil' => iso(600)],
])]);
$fake->on(
    'GET',
    '/auth/20260926-AU-[0-9-]+',
    [200, '{"status":{"code":100,"description":"Uwierzytelnianie w toku"}}'],
    [200, '{"status":{"code":200,"description":"Uwierzytelnianie zakonczone sukcesem"}}'],
);
$redeemed = 0;
$fake->on('POST', '/auth/token/redeem', static function () use (&$redeemed): array {
    $redeemed++;
    return [200, json_encode([
        'accessToken' => ['token' => "access-token-{$redeemed}", 'validUntil' => iso(900)],
        'refreshToken' => ['token' => "refresh-token-{$redeemed}", 'validUntil' => iso(7 * 86400)],
    ])];
});
$fake->on('POST', '/auth/token/refresh', [200, json_encode(['accessToken' => ['token' => 'refreshed-access-token', 'validUntil' => iso(900)]])]);

$client = static fn (string $forNip = '9999999999'): ApiClient => new ApiClient('test', $forNip, $shopCert, $shopKey, $fake(...), static function (int $seconds): void {
});

// --- Sealed credentials -----------------------------------------------------

[$encryptedKey] = selfSigned('unused');
[$lockedKey, $lockedCert] = selfSigned('Sklep z haslem', 'tajne haslo');

KSeFCredentials::save($lockedCert, $lockedKey, 'tajne haslo');
$stored = get_option(KSeFCredentials::OPTION);
$loaded = KSeFCredentials::load();

check('credentials save with the key and its passphrase', is_array($loaded));
check('the stored option holds neither the key nor the passphrase in the clear',
    ! str_contains(serialize($stored), 'PRIVATE KEY') && ! str_contains(serialize($stored), 'tajne haslo'));
check('load hands the signer an unencrypted key that matches the certificate',
    is_array($loaded) && ! str_contains($loaded['private_key'], 'ENCRYPTED')
    && openssl_x509_check_private_key($loaded['certificate'], $loaded['private_key']));

$refused = static function (callable $save): bool {
    try {
        $save();
    } catch (\InvalidArgumentException) {
        return true;
    }
    return false;
};
check('a wrong passphrase is refused', $refused(static fn () => KSeFCredentials::save($lockedCert, $lockedKey, 'zle')));
check('a key that belongs to another certificate is refused', $refused(static fn () => KSeFCredentials::save($lockedCert, $encryptedKey)));
check('a renewed certificate can keep the stored key, but only if it matches',
    $refused(static fn () => KSeFCredentials::save($shopCert, null)) && KSeFCredentials::load() !== null);

$GLOBALS['salt'] = 'salt-two';
check('rotated salts make the stored key unreadable instead of wrong', KSeFCredentials::load() === null);
$GLOBALS['salt'] = 'salt-one';
KSeFCredentials::clear();

// --- The Offline slot (certyfikaty-KSeF.md: one type per certificate, told by keyUsage)

/**
 * A certificate with a given keyUsage, like the ones KSeF issues.
 *
 * @return array{0: string, 1: string} Key PEM, certificate PEM.
 */
function typedCertificate(string $keyUsage, array $keySpec = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'], int $serial = 0x01F20A5D): array
{
    $config = tempnam(sys_get_temp_dir(), 'ksef-cnf');
    file_put_contents($config, "[req]\ndistinguished_name=dn\n[dn]\n[usage]\nkeyUsage=critical,{$keyUsage}\n");
    $key = openssl_pkey_new($keySpec);
    $csr = openssl_csr_new(['commonName' => 'Sklep Offline', 'countryName' => 'PL'], $key, ['config' => $config, 'digest_alg' => 'sha256']);
    $cert = openssl_csr_sign($csr, null, $key, 30, ['config' => $config, 'x509_extensions' => 'usage', 'digest_alg' => 'sha256'], $serial);
    unlink($config);
    openssl_x509_export($cert, $certPem);
    openssl_pkey_export($key, $keyPem);

    return [$keyPem, $certPem];
}

[$offlineKey, $offlineCert] = typedCertificate('nonRepudiation');
[$authKey, $authCert] = typedCertificate('digitalSignature');

check('the offline slot takes a Non-Repudiation certificate', ! $refused(static fn () => KSeFCredentials::save($offlineCert, $offlineKey, '', 'offline')));
check('the offline slot refuses an Authentication certificate', $refused(static fn () => KSeFCredentials::save($authCert, $authKey, '', 'offline')));
check('the offline slot refuses a certificate without keyUsage', $refused(static fn () => KSeFCredentials::save($shopCert, $shopKey, '', 'offline')));
check('the auth slot refuses an Offline certificate', $refused(static fn () => KSeFCredentials::save($offlineCert, $offlineKey)));
check('the auth slot still takes the self-signed TEST seal without keyUsage', ! $refused(static fn () => KSeFCredentials::save($shopCert, $shopKey)));
check('the auth slot takes a Digital Signature certificate', ! $refused(static fn () => KSeFCredentials::save($authCert, $authKey)));

try {
    KSeFCredentials::save($authCert, $authKey, '', 'offline');
} catch (\InvalidArgumentException $e) {
    check('the refusal says which type to paste', str_contains($e->getMessage(), 'Offline') && str_contains($e->getMessage(), 'Authentication'));
}

check('the slots do not overwrite each other', KSeFCredentials::certificate('offline') === $offlineCert && KSeFCredentials::certificate() === $authCert
    && get_option(KSeFCredentials::OFFLINE_OPTION) !== get_option(KSeFCredentials::OPTION));
check('the offline key loads for the KOD II signer', (KSeFCredentials::load('offline')['certificate'] ?? '') === $offlineCert);
check('the serial number is stored as even-length upper hex', (KSeFCredentials::details('offline')['serial'] ?? '') === '01F20A5D');
check('the expiry is stored next to it', abs((KSeFCredentials::details('offline')['valid_to'] ?? 0) - (time() + 30 * 86400)) < 120);
check('the serial helper pads and upper-cases', KSeFCredentials::serial('1f20a5d352ae590') === '01F20A5D352AE590' && KSeFCredentials::serial('0') === '00');
check('the offline option never lands in polski_pro_ksef', ! array_key_exists('polski_pro_ksef', $GLOBALS['options'] ?? []) || ! str_contains(serialize(get_option('polski_pro_ksef')), 'BEGIN'));

[$smallKey, $smallCert] = typedCertificate('nonRepudiation', ['private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
[$p384Key, $p384Cert] = typedCertificate('nonRepudiation', ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1']);
[$rsaOfflineKey, $rsaOfflineCert] = typedCertificate('nonRepudiation', ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
check('an RSA key under 2048 bits is refused', $refused(static fn () => KSeFCredentials::save($smallCert, $smallKey, '', 'offline')));
check('an EC key on another curve than P-256 is refused', $refused(static fn () => KSeFCredentials::save($p384Cert, $p384Key, '', 'offline')));
check('an RSA 2048 Offline certificate is taken', ! $refused(static fn () => KSeFCredentials::save($rsaOfflineCert, $rsaOfflineKey, '', 'offline')));

KSeFCredentials::clear('offline');
check('clearing the offline slot leaves the auth slot', KSeFCredentials::certificate('offline') === '' && KSeFCredentials::certificate() === $authCert);
KSeFCredentials::clear();

// --- Token lifecycle ----------------------------------------------------------

$api = $client();
$token = $api->accessToken();

check('a cold start authenticates and returns the redeemed access token', $token === 'access-token-1');
check('the XAdES chain runs in the documented order, polling while code is 100', $fake->calls() === [
    'POST /auth/challenge',
    'POST /auth/xades-signature',
    'GET /auth/20260926-AU-0000000001-0000000001-01',
    'GET /auth/20260926-AU-0000000001-0000000001-01',
    'POST /auth/token/redeem',
]);
check('the signed AuthTokenRequest goes as XML and carries the challenge and a signature',
    ($fake->log[1][2]['Content-Type'] ?? '') === 'application/xml'
    && str_contains((string) $fake->log[1][3], $challenge)
    && str_contains((string) $fake->log[1][3], 'SignatureValue'));
check('status and redeem use the operation token, not an access token',
    $fake->authorisation(2) === 'Bearer operation-token' && $fake->authorisation(4) === 'Bearer operation-token');
check('the token pair is stored sealed', ! str_contains((string) get_option(ApiClient::TOKEN_OPTION), 'access-token-1')
    && tokens()['refresh'] === 'refresh-token-1');

$before = count($fake->log);
check('a live access token is reused without a request', $api->accessToken() === 'access-token-1' && count($fake->log) === $before);

rewriteTokens(['access_until' => time() + 30]);
$before = count($fake->log);
check('an access token inside the expiry margin is refreshed with the refresh token',
    $api->accessToken() === 'refreshed-access-token' && $fake->calls($before) === ['POST /auth/token/refresh']
    && $fake->authorisation($before) === 'Bearer refresh-token-1');
check('refreshing keeps the refresh token', tokens()['refresh'] === 'refresh-token-1' && tokens()['access'] === 'refreshed-access-token');

rewriteTokens(['access_until' => time() - 1]);
$fake->on('POST', '/auth/token/refresh', [401, '{"title":"Unauthorized","detail":"Refresh token revoked"}']);
$before = count($fake->log);
check('a refused refresh falls back to a full authentication', $api->accessToken() === 'access-token-2'
    && $fake->calls($before)[0] === 'POST /auth/token/refresh' && in_array('POST /auth/challenge', $fake->calls($before), true));

rewriteTokens(['access_until' => time() - 1, 'refresh_until' => time() - 1]);
$before = count($fake->log);
check('an expired refresh token is not even tried', $api->accessToken() === 'access-token-3'
    && ! in_array('POST /auth/token/refresh', $fake->calls($before), true));

$before = count($fake->log);
check('tokens are scoped: another NIP does not reuse them', $client('1111111111')->accessToken() === 'access-token-4'
    && $fake->calls($before)[0] === 'POST /auth/challenge');

$fake->on('GET', '/sessions/S1/invoices/I1', [401, '{"title":"Unauthorized"}'], [200, '{"status":{"code":150,"description":"Trwa przetwarzanie"}}']);
$before = count($fake->log);
$status = $client()->invoiceStatus('S1', 'I1');
check('a 401 on a protected call re-authenticates once and retries', ApiClient::statusCode($status) === 150
    && in_array('POST /auth/challenge', $fake->calls($before), true) && end($fake->log)[1] === '/sessions/S1/invoices/I1');

// --- Sending, against a Ministry that decrypts what it gets ------------------

$fake->on('GET', '/security/public-key-certificates', [200, json_encode([
    ['certificate' => $ministryDer, 'publicKeyId' => str_repeat('K', 44), 'usage' => ['KsefTokenEncryption'], 'validFrom' => iso(-86400), 'validTo' => iso(86400)],
    ['certificate' => $ministryDer, 'publicKeyId' => str_repeat('S', 44), 'usage' => ['SymmetricKeyEncryption'], 'validFrom' => iso(-86400), 'validTo' => iso(86400)],
])]);

$session = [];
$fake->on('POST', '/sessions/online', static function (?string $body) use (&$session, $ministryPrivate): array {
    $request = json_decode((string) $body, true);
    $session = [
        'form' => $request['formCode']['systemCode'] ?? '',
        'keyId' => $request['encryption']['publicKeyId'] ?? '',
        'key' => $ministryPrivate->decrypt(base64_decode($request['encryption']['encryptedSymmetricKey'])),
        'iv' => base64_decode($request['encryption']['initializationVector']),
    ];
    return [201, json_encode(['referenceNumber' => 'SESSION-1', 'validUntil' => iso(3600)])];
});
$fake->on('POST', '/sessions/online/SESSION-1/invoices', static function (?string $body) use (&$session): array {
    $request = json_decode((string) $body, true);
    $plain = openssl_decrypt(base64_decode($request['encryptedInvoiceContent']), 'aes-256-cbc', $session['key'], OPENSSL_RAW_DATA, $session['iv']);
    $session['xml'] = $plain;
    $session['hashOk'] = $plain !== false && base64_encode(hash('sha256', $plain, true)) === $request['invoiceHash'];
    return [202, '{"referenceNumber":"INVOICE-1"}'];
});
$fake->on('POST', '/sessions/online/SESSION-1/close', [204, '']);
$ksefNumber = '9999999999-20260926-0A1B2C3D4E5F-AB';
$accepted = [200, json_encode(['ordinalNumber' => 1, 'referenceNumber' => 'INVOICE-1', 'ksefNumber' => $ksefNumber, 'status' => ['code' => 200, 'description' => 'Sukces']])];
$pending = [200, '{"ordinalNumber":1,"referenceNumber":"INVOICE-1","status":{"code":150,"description":"Trwa przetwarzanie"}}'];
$fake->on('GET', '/sessions/SESSION-1/invoices/INVOICE-1', $pending, $accepted);
$fake->on('GET', '/sessions/[^/]+/invoices/ksef/[^/]+/upo', [200, '<?xml version="1.0"?><Potwierdzenie>UPO</Potwierdzenie>']);

$GLOBALS['options']['polski_pro_invoices'] = [
    'seller_name' => 'Sklep Testowy sp. z o.o.',
    'seller_address' => 'ul. Prosta 1',
    'seller_postcode' => '00-001',
    'seller_city' => 'Warszawa',
];

$wpdb = new wpdb();
$newInvoice = static function (int $id) use ($wpdb, $nip): void {
    $wpdb->rows[$id] = (object) [
        'id' => $id, 'order_id' => 0, 'original_invoice_id' => null, 'source_refund_id' => null,
        'type' => 'faktura_vat', 'number' => "FV/2026/09/{$id}", 'status' => 'issued',
        'nip_seller' => $nip, 'nip_buyer' => null, 'correction_reason' => null,
        'net_total' => 100.0, 'vat_total' => 23.0, 'gross_total' => 123.0, 'currency' => 'PLN',
        'pdf_path' => null, 'ksef_reference' => null, 'ksef_status' => null,
        'issued_at' => '2026-09-26 10:00:00', 'created_at' => '2026-09-26 10:00:00',
        'buyer_name' => 'Jan Kowalski', 'buyer_address' => 'ul. Krotka 3', 'buyer_city' => 'Gdansk', 'buyer_postcode' => '80-001',
        'ksef_session' => null, 'ksef_upo' => null, 'ksef_error' => null,
    ];
    $wpdb->items[$id] = [(object) [
        'id' => $id, 'invoice_id' => $id, 'product_id' => null, 'name' => 'Licencja', 'quantity' => 1.0, 'unit' => 'szt',
        'net_price' => 100.0, 'vat_rate' => 23.0, 'vat_amount' => 23.0, 'gross_price' => 123.0, 'sort_order' => 0,
    ]];
};

$factoryClient = $client();
$service = new KSeFSyncService(new InvoiceRepository($wpdb), new FaMapper('Polski Pro test'), new FaValidator(), static fn (string $forNip): ?ApiClient => $factoryClient);

$newInvoice(1);
$before = count($fake->log);
$GLOBALS['scheduled'] = [];
$service->processSubmission(1);
$row = $wpdb->rows[1];

check('the session is opened for the form the document declares', ($session['form'] ?? '') === 'FA (3)');
check('the session key is wrapped for the SymmetricKeyEncryption certificate', ($session['keyId'] ?? '') === str_repeat('S', 44) && strlen((string) ($session['key'] ?? '')) === 32);
check('the Ministry side can decrypt the invoice and its hash matches', ($session['hashOk'] ?? false) === true
    && str_contains((string) ($session['xml'] ?? ''), '<P_2>FV/2026/09/1</P_2>'));
check('the invoice was polled until numbered and the session closed', in_array('POST /sessions/online/SESSION-1/close', $fake->calls($before), true));
check('the KSeF number lands in ksef_reference with status accepted', $row->ksef_reference === $ksefNumber && $row->ksef_status === 'accepted');
check('the session and the UPO are kept on the invoice', $row->ksef_session === 'SESSION-1' && str_contains((string) $row->ksef_upo, 'Potwierdzenie'));
check('nothing further is scheduled once the UPO is in', $GLOBALS['scheduled'] === [] && $row->ksef_error === null);
check('the accepted action fires', in_array(['polski/pro/ksef/accepted', 1], $GLOBALS['actions'], true));

$before = count($fake->log);
$service->processSubmission(1);
check('an invoice already in KSeF is never sent twice', count($fake->log) === $before);

// Still processing after the in-job wait: submitted, then a status check finishes it.
$fake->on('GET', '/sessions/SESSION-1/invoices/INVOICE-1', $pending);
$newInvoice(2);
$GLOBALS['scheduled'] = [];
$service->processSubmission(2);
check('a document still processing is recorded as submitted under its session reference',
    $wpdb->rows[2]->ksef_status === 'submitted' && $wpdb->rows[2]->ksef_reference === 'INVOICE-1' && $wpdb->rows[2]->ksef_session === 'SESSION-1');
check('and a status check is scheduled', ($GLOBALS['scheduled'][0][0] ?? '') === 'polski_pro_ksef_check_status'
    && ($GLOBALS['scheduled'][0][1] ?? []) === ['invoice_id' => 2, 'check' => 1]);

$fake->on('GET', '/sessions/SESSION-1/invoices/INVOICE-1', $accepted);
$service->processStatusCheck(2, 1);
check('the status check records the KSeF number and the UPO', $wpdb->rows[2]->ksef_reference === $ksefNumber
    && $wpdb->rows[2]->ksef_status === 'accepted' && $wpdb->rows[2]->ksef_upo !== null);

// Refused by KSeF's own rules.
$fake->on('GET', '/sessions/SESSION-1/invoices/INVOICE-1', [200, json_encode(['referenceNumber' => 'INVOICE-1', 'status' => [
    'code' => 450, 'description' => 'Blad weryfikacji semantyki dokumentu faktury', 'details' => ['Nieprawidlowy NIP nabywcy.'],
]])]);
$newInvoice(3);
$service->processSubmission(3);
check('a refusal is recorded with KSeF\'s reason for the merchant', $wpdb->rows[3]->ksef_status === 'rejected'
    && str_contains((string) $wpdb->rows[3]->ksef_error, '450') && str_contains((string) $wpdb->rows[3]->ksef_error, 'Nieprawidlowy NIP nabywcy.'));
check('and its reference is cleared so it can be sent again once fixed', $wpdb->rows[3]->ksef_reference === '');

// A duplicate from the session this row recorded is our own lost answer; take its number.
$fake->on('GET', '/sessions/SESSION-1/invoices/INVOICE-1', [200, json_encode(['referenceNumber' => 'INVOICE-1', 'status' => [
    'code' => 440, 'description' => 'Duplikat faktury',
    'extensions' => ['originalSessionReferenceNumber' => 'SESSION-0', 'originalKsefNumber' => '9999999999-20260925-FFFFFFFFFFFF-01'],
]])]);
$newInvoice(4);
$wpdb->rows[4]->ksef_session = 'SESSION-0';
$service->processSubmission(4);
check('a 440 duplicate is taken as accepted under the original number and session', $wpdb->rows[4]->ksef_status === 'accepted'
    && $wpdb->rows[4]->ksef_reference === '9999999999-20260925-FFFFFFFFFFFF-01' && $wpdb->rows[4]->ksef_session === 'SESSION-0');

// KSeF unreachable: bounded retries, then failed.
$fake->on('POST', '/sessions/online', static function (): array {
    throw new \RuntimeException('KSeF could not be reached: cURL error 28');
});
$newInvoice(5);
$GLOBALS['scheduled'] = [];
$service->processSubmission(5);
check('an unreachable KSeF queues a retry with backoff and says why', $wpdb->rows[5]->ksef_status === 'queued'
    && str_contains((string) $wpdb->rows[5]->ksef_error, 'cURL error 28')
    && ($GLOBALS['scheduled'][0][1] ?? []) === ['invoice_id' => 5, 'attempt' => 1, 'environment' => 'test', 'hold' => 0] && ($GLOBALS['scheduled'][0][2] ?? 0) >= 59);
$GLOBALS['scheduled'] = [];
$service->processSubmission(5, 3);
check('the last attempt gives up as failed and schedules nothing', $wpdb->rows[5]->ksef_status === 'failed' && $GLOBALS['scheduled'] === []);

// Not configured.
$unconfigured = new KSeFSyncService(new InvoiceRepository($wpdb), new FaMapper(), new FaValidator(), static fn (string $forNip): ?ApiClient => null);
$newInvoice(6);
$unconfigured->processSubmission(6);
check('without a certificate the invoice says so instead of failing silently', $wpdb->rows[6]->ksef_status === 'no_credentials'
    && str_contains((string) $wpdb->rows[6]->ksef_error, 'certificate'));

// A document the schema refuses never leaves the shop.
$newInvoice(7);
$wpdb->rows[7]->buyer_name = '';
$before = count($fake->log);
$service->processSubmission(7);
check('an unbuildable document is a mapping error and no request is made', $wpdb->rows[7]->ksef_status === 'mapping_error'
    && count($fake->log) === $before && (string) $wpdb->rows[7]->ksef_error !== '');

// --- Public key choice, key rotation, rate limits, TEST-only validation ------

$certificate = static fn (string $id, int $from, int $to, string $usage = 'SymmetricKeyEncryption'): array => [
    'certificate' => $ministryDer, 'publicKeyId' => $id, 'usage' => [$usage], 'validFrom' => iso($from), 'validTo' => iso($to),
];
$certificates = static fn (array ...$list): array => [200, json_encode($list)];
$opened = [];
$openRoute = static function (?string $body, array $headers) use (&$opened): array {
    $opened[] = ['keyId' => json_decode((string) $body, true)['encryption']['publicKeyId'] ?? '', 'headers' => $headers];
    return [201, json_encode(['referenceNumber' => 'SESSION-1', 'validUntil' => iso(3600)])];
};
$keyRejected = [400, json_encode(['title' => 'Bad Request', 'status' => 400, 'detail' => 'Blad', 'errors' => [[
    'code' => 21470, 'description' => 'Przeslany identyfikator klucza jest nieznany lub wskazuje na wycofany klucz.',
]]])];
$document = '<Faktura><Naglowek><KodFormularza kodSystemowy="FA (3)" wersjaSchemy="1-0E">FA</KodFormularza></Naglowek></Faktura>';
$fake->on('GET', '/sessions/SESSION-1/invoices/INVOICE-1', $accepted);
$fake->on('POST', '/sessions/online/SESSION-1/invoices', [202, '{"referenceNumber":"INVOICE-1"}']);
$fake->on('POST', '/sessions/online', $openRoute);

$GLOBALS['transients'] = [];
$fake->on('GET', '/security/public-key-certificates', $certificates(
    $certificate('EXPIRED', -20 * 86400, -86400),
    $certificate('OLDER', -10 * 86400, 300 * 86400),
    $certificate('TOKEN', -86400, 300 * 86400, 'KsefTokenEncryption'),
    $certificate('NEWER', -86400, 300 * 86400),
    $certificate('FUTURE', 86400, 400 * 86400),
));
$opened = [];
$client()->sendInvoice($document);
check('a certificate whose validFrom is still ahead is never used', ($opened[0]['keyId'] ?? '') !== 'FUTURE');
check('among valid certificates the latest validFrom wins', ($opened[0]['keyId'] ?? '') === 'NEWER');

$GLOBALS['transients'] = [];
$fake->on('GET', '/security/public-key-certificates', $certificates($certificate('ROTATED', -86400, 86400)), $certificates($certificate('CURRENT', -3600, 86400)));
$fake->on('POST', '/sessions/online', $keyRejected, $openRoute);
$opened = [];
$before = count($fake->log);
try {
    $result = $client()->sendInvoice($document);
} catch (\RuntimeException) {
    $result = ['session' => ''];
}
$calls = array_count_values($fake->calls($before));
check('21470 refetches the key list and retries the session once with the new key',
    $result['session'] === 'SESSION-1' && ($calls['GET /security/public-key-certificates'] ?? 0) === 2
    && ($calls['POST /sessions/online'] ?? 0) === 2 && ($opened[0]['keyId'] ?? '') === 'CURRENT');

$fake->on('POST', '/sessions/online', $keyRejected);
$before = count($fake->log);
$thrown = null;
try {
    $client()->sendInvoice($document);
} catch (\RuntimeException $e) {
    $thrown = $e;
}
$calls = array_count_values($fake->calls($before));
check('a second 21470 is not retried again but reported', $thrown !== null && $thrown->getCode() === 400
    && ($calls['POST /sessions/online'] ?? 0) === 2);
$fake->on('POST', '/sessions/online', $openRoute);

$fake->on('GET', '/sessions/S9/invoices/I9', [429, '{"status":{"code":429,"description":"Too Many Requests","details":["Przekroczono limit 20 zadan na minute."]}}', ['retry-after' => '30']]);
$limited = null;
try {
    $client()->invoiceStatus('S9', 'I9');
} catch (\RuntimeException $e) {
    $limited = $e;
}
check('429 throws KSeFRateLimited carrying Retry-After seconds', $limited instanceof KSeFRateLimited
    && $limited->retryAfter === 30 && $limited->getCode() === 429 && str_contains($limited->getMessage(), 'Przekroczono limit'));
check('Retry-After absent or an HTTP date still yields a sane wait', KSeFRateLimited::seconds('') === 60
    && abs(KSeFRateLimited::seconds(gmdate('D, d M Y H:i:s \G\M\T', time() + 120)) - 120) <= 2 && KSeFRateLimited::seconds('junk') === 60);

$opened = [];
$client()->sendInvoice($document);
$production = new ApiClient('production', $nip, $shopCert, $shopKey, $fake(...), static function (int $seconds): void {
});
$production->sendInvoice($document);
check('TEST asks KSeF to validate NIPs, production does not send the feature header',
    ($opened[0]['headers']['X-KSeF-Feature'] ?? '') === 'subject-identifier-validation'
    && ! isset($opened[1]['headers']['X-KSeF-Feature']));
check('environment() is normalised', $production->environment() === 'production' && $client()->environment() === 'test'
    && (new ApiClient('Production ', $nip, $shopCert, $shopKey, $fake(...)))->environment() === 'test');

echo "\n{$checks} checks, {$failures} failed\n";

exit($failures === 0 ? 0 : 1);
