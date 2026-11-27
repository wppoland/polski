<?php

declare(strict_types=1);

/**
 * KSeFSyncService decisions on the send path, against a fake Ministry.
 *
 *   php tests/ksef-sync-test.php
 *
 * Covers what the row ends up saying: KOD I over the bytes KSeF received, a
 * 440 duplicate that is ours versus one that is someone else's, when a
 * correction may go, which environment an invoice belongs to, and a 429 that
 * reschedules without costing an attempt. The fake decrypts what it receives,
 * so the hash is checked against the bytes the Ministry actually saw.
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
    return 'salt-' . $scheme;
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

/** The invoices table, keyed by id; records the SQL it is asked. */
final class wpdb
{
    public string $prefix = 'wp_';
    /** @var array<int, object> */
    public array $rows = [];
    /** @var array<int, list<object>> */
    public array $items = [];
    /** @var list<string> */
    public array $queries = [];

    /** @return array{0: string, 1: array<int, mixed>} */
    public function prepare(string $query, mixed ...$args): array
    {
        $this->queries[] = $query;
        return [$query, $args];
    }

    public function get_row(array $prepared): ?object
    {
        return isset($this->rows[$prepared[1][1]]) ? clone $this->rows[$prepared[1][1]] : null;
    }

    /** @return list<object> */
    public function get_results(array $prepared): array
    {
        return str_contains($prepared[0], 'invoice_id') ? ($this->items[$prepared[1][1]] ?? []) : [];
    }

    public function get_var(array $prepared): mixed
    {
        return str_contains($prepared[0], 'ksef_upo') ? ($this->rows[$prepared[1][1]]->ksef_upo ?? null) : 0;
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
require $root . 'src/KSeF/OfflineDeadline.php';
require $root . 'src/KSeF/KSeFSyncService.php';
require $root . 'src/KSeF/Admin/KSeFOrderBox.php';

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Polski\KSeF\KSeFSyncService;
use Polski\KSeF\V2\ApiClient;
use Polski\KSeF\V2\FaMapper;
use Polski\KSeF\V2\FaValidator;
use Polski\KSeF\V2\KSeFCredentials;
use Polski\KSeF\V2\KSeFCrypto;
use Polski\KSeF\V2\VerificationLink;
use Polski\KSeF\Model\Invoice;
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
function selfSigned(string $commonName): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => $commonName, 'countryName' => 'PL'], $key, ['digest_alg' => 'sha256']);
    $cert = openssl_csr_sign($csr, null, $key, 30, ['digest_alg' => 'sha256']);
    openssl_x509_export($cert, $certPem);
    openssl_pkey_export($key, $keyPem);

    return [$keyPem, $certPem];
}

function iso(int $offset): string
{
    return gmdate('Y-m-d\TH:i:s\Z', time() + $offset);
}

/** Routes by method and path pattern; the last answer of a route repeats. */
final class FakeKsef
{
    /** @var list<array{0: string, 1: string}> */
    public array $log = [];
    /** @var array<string, list<mixed>> */
    public array $routes = [];

    public function on(string $method, string $pattern, mixed ...$answers): void
    {
        $this->routes["{$method} {$pattern}"] = $answers;
    }

    /** @param array<string, string> $headers */
    public function __invoke(string $method, string $url, array $headers, ?string $body): array
    {
        $path = (string) preg_replace('#^https://api(-test)?\.ksef\.mf\.gov\.pl/v2#', '', $url);
        $this->log[] = [$method, $path];

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

    public function sent(int $from): bool
    {
        foreach (array_slice($this->log, $from) as [$method, $path]) {
            if ($method === 'POST' && $path === '/sessions/online') {
                return true;
            }
        }
        return false;
    }
}

$nip = '9999999999';
[$shopKey, $shopCert] = selfSigned('Sklep Testowy');
[$ministryKey, $ministryCert] = selfSigned('Ministerstwo Finansow');
$ministryDer = preg_replace('/-----[A-Z ]+-----|\s/', '', $ministryCert);
$ministryPrivate = PublicKeyLoader::loadPrivateKey($ministryKey)
    ->withPadding(RSA::ENCRYPTION_OAEP)->withHash('sha256')->withMGFHash('sha256');

$fake = new FakeKsef();
$fake->on('POST', '/auth/challenge', [200, json_encode(['challenge' => '20260926-CR-ABCDEF0123-456789ABCD-EF', 'timestamp' => iso(0), 'timestampMs' => time() * 1000])]);
$fake->on('POST', '/auth/xades-signature', [202, json_encode([
    'referenceNumber' => '20260926-AU-0000000001-0000000001-01',
    'authenticationToken' => ['token' => 'operation-token', 'validUntil' => iso(600)],
])]);
$fake->on('GET', '/auth/20260926-AU-[0-9-]+', [200, '{"status":{"code":200,"description":"OK"}}']);
$fake->on('POST', '/auth/token/redeem', [200, json_encode([
    'accessToken' => ['token' => 'access', 'validUntil' => iso(900)],
    'refreshToken' => ['token' => 'refresh', 'validUntil' => iso(7 * 86400)],
])]);
$fake->on('GET', '/security/public-key-certificates', [200, json_encode([
    ['certificate' => $ministryDer, 'publicKeyId' => str_repeat('S', 44), 'usage' => ['SymmetricKeyEncryption'], 'validFrom' => iso(-86400), 'validTo' => iso(86400)],
])]);

$session = [];
$openSession = static function (?string $body) use (&$session, $ministryPrivate): array {
    $request = json_decode((string) $body, true);
    $session = [
        'key' => $ministryPrivate->decrypt(base64_decode($request['encryption']['encryptedSymmetricKey'])),
        'iv' => base64_decode($request['encryption']['initializationVector']),
    ];
    return [201, json_encode(['referenceNumber' => 'SESSION-1', 'validUntil' => iso(3600)])];
};
$fake->on('POST', '/sessions/online', $openSession);
$fake->on('POST', '/sessions/online/SESSION-1/invoices', static function (?string $body) use (&$session): array {
    $request = json_decode((string) $body, true);
    $session['request'] = $request;
    $session['xml'] = openssl_decrypt(base64_decode($request['encryptedInvoiceContent']), 'aes-256-cbc', $session['key'], OPENSSL_RAW_DATA, $session['iv']);
    return [202, '{"referenceNumber":"INVOICE-1"}'];
});
$fake->on('POST', '/sessions/online/SESSION-1/close', [204, '']);
$fake->on('GET', '/sessions/[^/]+/invoices/ksef/[^/]+/upo', [200, '<?xml version="1.0"?><Potwierdzenie>UPO</Potwierdzenie>']);

$ksefNumber = '9999999999-20260926-0A1B2C3D4E5F-AB';
$accepted = [200, json_encode(['referenceNumber' => 'INVOICE-1', 'ksefNumber' => $ksefNumber, 'status' => ['code' => 200, 'description' => 'Sukces']])];
$pending = [200, '{"referenceNumber":"INVOICE-1","status":{"code":150,"description":"Trwa przetwarzanie"}}'];
$duplicate = static fn (string $originalSession): array => [200, json_encode(['referenceNumber' => 'INVOICE-1', 'status' => [
    'code' => 440, 'description' => 'Duplikat faktury',
    'extensions' => ['originalSessionReferenceNumber' => $originalSession, 'originalKsefNumber' => '9999999999-20260925-FFFFFFFFFFFF-01'],
]])];
$answer = static function (array $status) use ($fake): void {
    $fake->on('GET', '/sessions/SESSION-1/invoices/INVOICE-1', $status);
};

$GLOBALS['options']['polski_pro_invoices'] = [
    'seller_name' => 'Sklep Testowy sp. z o.o.',
    'seller_address' => 'ul. Prosta 1',
    'seller_postcode' => '00-001',
    'seller_city' => 'Warszawa',
];

$wpdb = new wpdb();
$newInvoice = static function (int $id, array $fields = []) use ($wpdb, $nip): void {
    $wpdb->rows[$id] = (object) array_merge([
        'id' => $id, 'order_id' => 0, 'original_invoice_id' => null, 'source_refund_id' => null,
        'type' => 'faktura_vat', 'number' => "FV/2026/09/{$id}", 'status' => 'issued',
        'nip_seller' => $nip, 'nip_buyer' => null, 'correction_reason' => null,
        'net_total' => 100.0, 'vat_total' => 23.0, 'gross_total' => 123.0, 'currency' => 'PLN',
        'pdf_path' => null, 'ksef_reference' => null, 'ksef_status' => null,
        'issued_at' => '2026-09-26 10:00:00', 'created_at' => '2026-09-26 10:00:00',
        'buyer_name' => 'Jan Kowalski', 'buyer_address' => 'ul. Krotka 3', 'buyer_city' => 'Gdansk', 'buyer_postcode' => '80-001',
        'ksef_session' => null, 'ksef_upo' => null, 'ksef_error' => null, 'ksef_environment' => null, 'ksef_qr_url' => null,
    ], $fields);
    $wpdb->items[$id] = [(object) [
        'id' => $id, 'invoice_id' => $id, 'product_id' => null, 'name' => 'Licencja', 'quantity' => 1.0, 'unit' => 'szt',
        'net_price' => 100.0, 'vat_rate' => 23.0, 'vat_amount' => 23.0, 'gross_price' => 123.0, 'sort_order' => 0,
    ]];
};
$correctionOf = static function (int $id, int $original) use ($newInvoice, $wpdb): void {
    $newInvoice($id, ['type' => 'korygujaca', 'original_invoice_id' => $original, 'number' => "KOR/2026/09/{$id}", 'correction_reason' => 'Zwrot']);
    $wpdb->items[$id][0]->quantity = 0.0;
    $wpdb->items[$id][0]->net_price = 0.0;
    $wpdb->items[$id][0]->vat_amount = 0.0;
    $wpdb->items[$id][0]->gross_price = 0.0;
    $wpdb->rows[$id]->net_total = -100.0;
    $wpdb->rows[$id]->vat_total = -23.0;
    $wpdb->rows[$id]->gross_total = -123.0;
};

$clients = [];
$clientFor = static function (string $environment) use (&$clients, $shopCert, $shopKey, $fake): ApiClient {
    return $clients[$environment] ??= new ApiClient($environment, '9999999999', $shopCert, $shopKey, $fake(...), static function (int $seconds): void {
    });
};
$rendered = [];
$renderPdf = static function (Invoice $invoice) use (&$rendered): string {
    $rendered[] = $invoice;
    return "/uploads/polski-invoices/{$invoice->id}.pdf";
};
$serviceFor = static fn (string $environment): KSeFSyncService => new KSeFSyncService(
    new InvoiceRepository($wpdb),
    new FaMapper('Polski Pro test'),
    new FaValidator(),
    static fn (string $forNip): ?ApiClient => $clientFor($environment),
    $renderPdf,
);
$service = $serviceFor('test');

// --- KOD I and the regenerated PDF -------------------------------------------

$answer($accepted);
$newInvoice(1);
$service->processSubmission(1);
$row = $wpdb->rows[1];
$expected = VerificationLink::url('test', new DateTimeImmutable('2026-09-26'), '9999999999', VerificationLink::hash((string) ($session['xml'] ?? '')));

check('accepted invoice stores the KOD I link', $row->ksef_status === 'accepted' && $row->ksef_qr_url === $expected);
check('the link hash is over the exact bytes the Ministry decrypted', str_ends_with((string) $row->ksef_qr_url, '/' . VerificationLink::hash((string) $session['xml'])) && strlen((string) $session['xml']) > 100);
check('the link points at the TEST QR host with NIP and P_1', str_starts_with((string) $row->ksef_qr_url, 'https://qr-test.ksef.mf.gov.pl/invoice/9999999999/26-09-2026/'));
check('the environment is recorded on the row', $row->ksef_environment === 'test');
check('the PDF is regenerated once, seeing the KSeF number and the link', count($rendered) === 1
    && $rendered[0]->ksefReference === $ksefNumber && $rendered[0]->ksefQrUrl === $expected);
check('the regenerated PDF path is stored', $row->pdf_path === '/uploads/polski-invoices/1.pdf');

$answer($pending);
$newInvoice(2);
$rendered = [];
$service->processSubmission(2);
check('a pending invoice already keeps its link, but gets no PDF yet', $wpdb->rows[2]->ksef_status === 'submitted'
    && str_starts_with((string) $wpdb->rows[2]->ksef_qr_url, 'https://qr-test') && $rendered === []);
$answer($accepted);
$GLOBALS['scheduled'] = [];
$service->processStatusCheck(2, 1);
check('the status check keeps the link and then regenerates the PDF', $wpdb->rows[2]->ksef_status === 'accepted'
    && str_starts_with((string) $wpdb->rows[2]->ksef_qr_url, 'https://qr-test') && count($rendered) === 1);

// --- 440 duplicate -------------------------------------------------------------

$answer($duplicate('SESSION-0'));
$newInvoice(3);
$GLOBALS['actions'] = [];
$service->processSubmission(3);
$row = $wpdb->rows[3];
check('440 without an earlier send by us is rejected, not accepted', $row->ksef_status === 'rejected' && $row->ksef_reference === '');
check('the error names the colliding KSeF number', str_contains((string) $row->ksef_error, '9999999999-20260925-FFFFFFFFFFFF-01'));
check('no link is left on a rejected duplicate', $row->ksef_qr_url === null);
check('the rejected action fires, the accepted one does not', in_array('polski/pro/ksef/rejected', array_column($GLOBALS['actions'], 0), true)
    && ! in_array('polski/pro/ksef/accepted', array_column($GLOBALS['actions'], 0), true));

$newInvoice(4, ['ksef_session' => 'SESSION-7', 'ksef_status' => 'rejected', 'ksef_reference' => '']);
$service->processSubmission(4);
check('440 from a session other than the one this row recorded is rejected', $wpdb->rows[4]->ksef_status === 'rejected');

$newInvoice(5, ['ksef_session' => 'SESSION-0', 'ksef_status' => 'queued', 'ksef_reference' => '']);
$rendered = [];
$service->processSubmission(5);
$row = $wpdb->rows[5];
check('440 from the session this row recorded is ours: accepted under the original number', $row->ksef_status === 'accepted'
    && $row->ksef_reference === '9999999999-20260925-FFFFFFFFFFFF-01' && $row->ksef_session === 'SESSION-0');
check('no link is written on the 440 path, the bytes KSeF holds are unknown', $row->ksef_qr_url === null);

// --- Correction gating -----------------------------------------------------------

$answer($accepted);
$gate = static function (int $id, array $original, int $hold = 0) use ($newInvoice, $correctionOf, $fake, $service): bool {
    $newInvoice($id + 100, $original);
    $correctionOf($id, $id + 100);
    $GLOBALS['scheduled'] = [];
    $before = count($fake->log);
    $service->processSubmission($id, 0, 'test', $hold);
    return $fake->sent($before);
};

$sent = $gate(10, ['ksef_status' => 'accepted', 'ksef_reference' => $ksefNumber, 'ksef_environment' => 'test']);
check('original numbered in this environment: correction is sent citing it', $sent && $wpdb->rows[10]->ksef_status === 'accepted'
    && str_contains((string) $session['xml'], '<NrKSeFFaKorygowanej>' . $ksefNumber . '</NrKSeFFaKorygowanej>'));

$sent = $gate(11, ['ksef_status' => 'submitted', 'ksef_reference' => 'INVOICE-9', 'ksef_environment' => 'test', 'ksef_session' => 'SESSION-9']);
check('original submitted but not numbered: correction is held, nothing sent', ! $sent && $wpdb->rows[11]->ksef_status === 'queued');
check('and requeued for later with the hold counted and the environment kept', ($GLOBALS['scheduled'][0][1] ?? []) === ['invoice_id' => 11, 'attempt' => 0, 'environment' => 'test', 'hold' => 1]
    && ($GLOBALS['scheduled'][0][2] ?? 0) >= 119);

$sent = $gate(12, ['ksef_status' => 'queued', 'ksef_reference' => ''], 29);
check('the hold is bounded: the last one fails the correction', ! $sent && $wpdb->rows[12]->ksef_status === 'failed' && $GLOBALS['scheduled'] === []);

$sent = $gate(13, []);
check('original never sent to KSeF: correction goes with NrKSeFN', $sent && str_contains((string) $session['xml'], '<NrKSeFN>1</NrKSeFN>'));

$sent = $gate(14, ['ksef_status' => 'no_nip', 'ksef_reference' => '']);
check('original stopped before KSeF (no_nip): correction goes with NrKSeFN', $sent && str_contains((string) $session['xml'], '<NrKSeFN>1</NrKSeFN>'));

$sent = $gate(15, ['ksef_status' => 'rejected', 'ksef_reference' => '']);
check('original rejected: correction fails, nothing sent', ! $sent && $wpdb->rows[15]->ksef_status === 'failed'
    && str_contains((string) $wpdb->rows[15]->ksef_error, 'File it first'));

$sent = $gate(16, ['ksef_status' => 'failed', 'ksef_reference' => '']);
check('original failed: correction fails, nothing sent', ! $sent && $wpdb->rows[16]->ksef_status === 'failed');

// --- Environment per invoice ----------------------------------------------------

$production = $serviceFor('production');

$newInvoice(20);
$before = count($fake->log);
$GLOBALS['scheduled'] = [];
$service->processSubmission(20, 0, 'production');
check('a job queued for production does not send on TEST', ! $fake->sent($before) && $GLOBALS['scheduled'] === []);
check('it goes back to queued and tells the merchant to send again', $wpdb->rows[20]->ksef_status === 'queued'
    && str_contains((string) $wpdb->rows[20]->ksef_error, 'send it again') && str_contains((string) $wpdb->rows[20]->ksef_error, 'production'));

$newInvoice(21, ['ksef_status' => 'submitted', 'ksef_reference' => 'INVOICE-1', 'ksef_session' => 'SESSION-1', 'ksef_environment' => 'test']);
$before = count($fake->log);
$GLOBALS['scheduled'] = [];
$production->processStatusCheck(21, 3);
check('a status check never asks the other environment', count($fake->log) === $before
    && str_contains((string) $wpdb->rows[21]->ksef_error, 'KSeF test') && $wpdb->rows[21]->ksef_reference === 'INVOICE-1');
check('and keeps checking in case the setting goes back', ($GLOBALS['scheduled'][0][1] ?? []) === ['invoice_id' => 21, 'check' => 4]);

$GLOBALS['options']['polski_pro_ksef'] = ['enabled' => true, 'environment' => 'test'];
check('an invoice accepted on TEST is refused again on TEST', $service->queueForSending(1)['code'] === 409);
$GLOBALS['options']['polski_pro_ksef'] = ['enabled' => true, 'environment' => 'production'];
$GLOBALS['scheduled'] = [];
$queued = $production->queueForSending(1);
check('but can be queued once the store is on production', $queued['code'] === 202
    && ($GLOBALS['scheduled'][0][1] ?? []) === ['invoice_id' => 1, 'attempt' => 0, 'environment' => 'production', 'hold' => 0]);
check('queueing keeps the TEST number until production answers', $wpdb->rows[1]->ksef_reference === $ksefNumber);
$before = count($fake->log);
$production->processSubmission(1, 0, 'production');
$row = $wpdb->rows[1];
check('the production send goes out and is recorded as production', $fake->sent($before) && $row->ksef_environment === 'production' && $row->ksef_status === 'accepted');
check('with a production KOD I link', str_starts_with((string) $row->ksef_qr_url, 'https://qr.ksef.mf.gov.pl/invoice/'));

$newInvoice(122, ['ksef_status' => 'accepted', 'ksef_reference' => $ksefNumber, 'ksef_environment' => 'test']);
$correctionOf(22, 122);
$before = count($fake->log);
$production->processSubmission(22, 0, 'production');
check('a production correction never cites a TEST number: it fails, nothing sent', ! $fake->sent($before)
    && $wpdb->rows[22]->ksef_status === 'failed' && str_contains((string) $wpdb->rows[22]->ksef_error, 'KSeF test only'));

$newInvoice(23, ['ksef_status' => 'accepted', 'ksef_reference' => $ksefNumber, 'ksef_environment' => null]);
$before = count($fake->log);
$production->processSubmission(23, 0, 'production');
check('a row from before the environment column counts as filed, never resent', count($fake->log) === $before && $wpdb->rows[23]->ksef_reference === $ksefNumber);

// --- queueForSending gates ------------------------------------------------------

$newInvoice(30);
$GLOBALS['options']['polski_pro_ksef'] = ['enabled' => false, 'environment' => 'test'];
check('switched off in settings: not queued', $service->queueForSending(30)['code'] === 409);
$GLOBALS['options']['polski_pro_ksef'] = ['enabled' => true, 'environment' => 'test'];
$bare = new KSeFSyncService(new InvoiceRepository($wpdb), new FaMapper(), new FaValidator(), static fn (string $forNip): ?ApiClient => null, $renderPdf);
check('no certificate: not queued', $bare->queueForSending(30)['code'] === 409);
check('unknown invoice: 404', $service->queueForSending(999)['code'] === 404);
$GLOBALS['scheduled'] = [];
check('enabled with a certificate: queued as test', $service->queueForSending(30)['code'] === 202
    && ($GLOBALS['scheduled'][0][1]['environment'] ?? '') === 'test' && $wpdb->rows[30]->ksef_status === 'queued');

// --- Rate limit -------------------------------------------------------------------

$fake->on('POST', '/sessions/online', [429, '{"status":{"code":429,"description":"Too Many Requests"}}', ['retry-after' => '90']]);
$newInvoice(40);
$GLOBALS['scheduled'] = [];
$service->processSubmission(40, 3, 'test');
check('429 on the last attempt does not fail the invoice', $wpdb->rows[40]->ksef_status === 'queued' && str_contains((string) $wpdb->rows[40]->ksef_error, '90'));
check('it is rescheduled no earlier than Retry-After, same attempt', ($GLOBALS['scheduled'][0][1] ?? []) === ['invoice_id' => 40, 'attempt' => 3, 'environment' => 'test', 'hold' => 0]
    && ($GLOBALS['scheduled'][0][2] ?? 0) >= 89);
$fake->on('POST', '/sessions/online', $openSession);

$fake->on('GET', '/sessions/SESSION-1/invoices/INVOICE-1', [429, '{"status":{"code":429,"description":"Too Many Requests"}}', ['retry-after' => '300']]);
$newInvoice(41, ['ksef_status' => 'submitted', 'ksef_reference' => 'INVOICE-1', 'ksef_session' => 'SESSION-1', 'ksef_environment' => 'test']);
$GLOBALS['scheduled'] = [];
$service->processStatusCheck(41, 5);
check('429 on a status check reschedules after Retry-After without counting a check', ($GLOBALS['scheduled'][0][1] ?? []) === ['invoice_id' => 41, 'check' => 5]
    && ($GLOBALS['scheduled'][0][2] ?? 0) >= 299 && $wpdb->rows[41]->ksef_status === 'submitted');

// --- KSeF down: hourly retries instead of a terminal failure -----------------------

$fake->on('POST', '/sessions/online', [503, '{"title":"Service Unavailable","detail":"Przerwa techniczna"}']);
$newInvoice(42);
$GLOBALS['scheduled'] = [];
$service->processSubmission(42, 3, 'test');
check('503 on the last quick attempt keeps the invoice queued, not failed', $wpdb->rows[42]->ksef_status === 'queued'
    && str_contains((string) $wpdb->rows[42]->ksef_error, 'every hour'));
check('and resends it an hour later, next attempt, same environment', ($GLOBALS['scheduled'][0][1] ?? []) === ['invoice_id' => 42, 'attempt' => 4, 'environment' => 'test', 'hold' => 0]
    && ($GLOBALS['scheduled'][0][2] ?? 0) >= 3599);
$GLOBALS['scheduled'] = [];
$GLOBALS['actions'] = [];
$service->processSubmission(42, 51, 'test');
check('the outage retries are bounded: after two days of hourly sends it fails', $wpdb->rows[42]->ksef_status === 'failed'
    && $GLOBALS['scheduled'] === [] && in_array('polski/pro/ksef/rejected', array_column($GLOBALS['actions'], 0), true));

$fake->on('POST', '/sessions/online', [400, '{"title":"Bad Request","detail":"Nieprawidlowe dane"}']);
$newInvoice(43);
$GLOBALS['scheduled'] = [];
$service->processSubmission(43, 3, 'test');
check('a refusal (400) on the last attempt still fails at once, nothing resent', $wpdb->rows[43]->ksef_status === 'failed' && $GLOBALS['scheduled'] === []);
$fake->on('POST', '/sessions/online', $openSession);

// --- refreshStatus and upoXml -------------------------------------------------------

$answer($accepted);
$GLOBALS['scheduled'] = [];
$refreshed = $service->refreshStatus(41);
check('refreshStatus records the verdict now and schedules nothing', $refreshed?->ksefStatus === 'accepted' && $GLOBALS['scheduled'] === []);
check('upoXml hands back the receipt', str_contains((string) $service->upoXml(41), 'Potwierdzenie') && $service->upoXml(30) === null);

// --- Offline invoices (tryby-offline.md, kody-qr.md, offline/korekta-techniczna.md) --

$config = tempnam(sys_get_temp_dir(), 'ksef-cnf');
file_put_contents($config, "[req]\ndistinguished_name=dn\n[dn]\n[usage]\nkeyUsage=critical,nonRepudiation\n");
$offlineKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
$offlineCsr = openssl_csr_new(['commonName' => 'Sklep Offline', 'countryName' => 'PL'], $offlineKey, ['config' => $config, 'digest_alg' => 'sha256']);
openssl_x509_export(openssl_csr_sign($offlineCsr, null, $offlineKey, 30, ['config' => $config, 'x509_extensions' => 'usage', 'digest_alg' => 'sha256'], 0x01F20A5D), $offlineCertPem);
openssl_pkey_export($offlineKey, $offlineKeyPem);
unlink($config);
$GLOBALS['options']['polski_pro_ksef'] = ['enabled' => true, 'environment' => 'test', 'offline_mode' => 'offline24'];

$newInvoice(50);
check('without an Offline certificate an invoice is not issued offline', $service->issueOffline(50, 'offline24')['code'] === 422
    && $wpdb->rows[50]->ksef_status === 'mapping_error' && empty($wpdb->rows[50]->ksef_xml));
$newInvoice(50);
KSeFCredentials::save($offlineCertPem, $offlineKeyPem, '', 'offline');

$GLOBALS['scheduled'] = [];
$rendered = [];
$issued = $service->issueOffline(50, 'offline24');
$row = clone $wpdb->rows[50];
$frozen = (string) ($row->ksef_xml ?? '');
check('issueOffline answers 202', $issued['code'] === 202);
check('the XML is frozen and schema valid', strlen($frozen) > 100 && (new FaValidator())->errors($frozen) === []);
check('the first hash is kept in plain Base64', $row->ksef_offline_hash === KSeFCrypto::hash($frozen));
check('KOD I is over the frozen bytes', $row->ksef_qr_url === VerificationLink::url('test', new DateTimeImmutable('2026-09-26'), '9999999999', VerificationLink::hash($frozen)));
check('KOD II names the Nip context, the seller, the serial and the same hash', str_starts_with((string) $row->ksef_cert_url,
    'https://qr-test.ksef.mf.gov.pl/certificate/Nip/9999999999/9999999999/01F20A5D/' . VerificationLink::hash($frozen) . '/'));
$certPath = substr((string) $row->ksef_cert_url, strlen('https://'), strrpos((string) $row->ksef_cert_url, '/') - strlen('https://'));
$certSig = (string) base64_decode(strtr(substr((string) $row->ksef_cert_url, strrpos((string) $row->ksef_cert_url, '/') + 1), '-_', '+/'), true);
$der = static function (string $raw): string {
    $int = static fn (string $b): string => "\x02" . chr(strlen($b = (ord(($b = ltrim($b, "\0") ?: "\0")[0]) > 0x7f ? "\0" . $b : $b))) . $b;
    $body = $int(substr($raw, 0, 32)) . $int(substr($raw, 32));
    return "\x30" . chr(strlen($body)) . $body;
};
check('the KOD II signature verifies with the Offline certificate', openssl_verify($certPath, $der($certSig), openssl_pkey_get_public($offlineCertPem), OPENSSL_ALGO_SHA256) === 1);
check('mode offline24, deadline the next working day (P_1 26.09.2026 is a Saturday), already past so overdue', $row->ksef_status === 'offline_overdue'
    && $row->ksef_offline_mode === 'offline24' && $row->ksef_offline_deadline === '2026-09-28 21:59:59');
check('nothing is sent at issue, the PDF is regenerated with both codes, the send is queued', $rendered !== []
    && $rendered[0]->ksefCertUrl === $row->ksef_cert_url && $rendered[0]->ksefQrUrl === $row->ksef_qr_url
    && ($GLOBALS['scheduled'][0][1] ?? []) === ['invoice_id' => 50, 'attempt' => 0, 'environment' => 'test', 'hold' => 0]);
check('issuing twice is refused', $service->issueOffline(50, 'offline24')['code'] === 409);

$answer($accepted);
$rendered = [];
$service->processSubmission(50, 0, 'test');
$after = $wpdb->rows[50];
check('the send carries the frozen bytes, byte for byte', ($session['xml'] ?? '') === $frozen);
check('with offlineMode true and no hashOfCorrectedInvoice', ($session['request']['offlineMode'] ?? null) === true
    && ! array_key_exists('hashOfCorrectedInvoice', $session['request'] ?? []));
check('accepted: KOD I is the same link as on the issued PDF', $after->ksef_status === 'accepted' && $after->ksef_qr_url === $row->ksef_qr_url);
check('KOD II and the frozen XML stay', $after->ksef_cert_url === $row->ksef_cert_url && $after->ksef_xml === $frozen && $after->ksef_offline_hash === $row->ksef_offline_hash);
check('the regenerated PDF has the KSeF number and both codes', ($rendered[0] ?? null)?->ksefReference === $ksefNumber && $rendered[0]->ksefCertUrl === $row->ksef_cert_url);

// Online invoices are unchanged: no offlineMode.
$newInvoice(51);
$service->processSubmission(51, 0, 'test');
check('an online invoice is still sent with offlineMode false and built at send time', ($session['request']['offlineMode'] ?? null) === false
    && empty($wpdb->rows[51]->ksef_xml) && empty($wpdb->rows[51]->ksef_cert_url));

// Rejected offline invoice and technical correction.
$newInvoice(52);
$service->issueOffline(52, 'offline24');
$issuedRow = clone $wpdb->rows[52];
$answer([200, json_encode(['referenceNumber' => 'INVOICE-1', 'status' => ['code' => 450, 'description' => 'Blad weryfikacji semantyki dokumentu faktury']])]);
$service->processSubmission(52, 0, 'test');
$rejected = $wpdb->rows[52];
check('a rejected offline invoice keeps its XML, hash and both codes', $rejected->ksef_status === 'rejected'
    && $rejected->ksef_xml === $issuedRow->ksef_xml && $rejected->ksef_offline_hash === $issuedRow->ksef_offline_hash
    && $rejected->ksef_qr_url === $issuedRow->ksef_qr_url && $rejected->ksef_cert_url === $issuedRow->ksef_cert_url);
check('the error carries KSeF words and points to the technical correction', str_contains((string) $rejected->ksef_error, '450') && str_contains((string) $rejected->ksef_error, 'technical correction'));
check('a technical correction is refused for an online invoice', $service->technicalCorrection(51)['code'] === 409);

$GLOBALS['scheduled'] = [];
sleep(1); // DataWytworzeniaFa has one second resolution; a rebuild in the same second gives the same bytes.
check('a technical correction of the rejected offline invoice is queued', $service->technicalCorrection(52)['code'] === 202 && ($GLOBALS['scheduled'][0][1]['invoice_id'] ?? 0) === 52);
$corrected = $wpdb->rows[52];
check('it rebuilds the XML (new hash) and keeps the first hash', $corrected->ksef_xml !== $issuedRow->ksef_xml && $corrected->ksef_offline_hash === $issuedRow->ksef_offline_hash
    && in_array($corrected->ksef_status, ['offline', 'offline_overdue'], true));
check('KOD I and KOD II move together to the new bytes', str_ends_with((string) $corrected->ksef_qr_url, '/' . VerificationLink::hash((string) $corrected->ksef_xml))
    && str_contains((string) $corrected->ksef_cert_url, '/' . VerificationLink::hash((string) $corrected->ksef_xml) . '/'));
$answer($accepted);
$service->processSubmission(52, 0, 'test');
check('the correction goes with offlineMode and hashOfCorrectedInvoice = the rejected file hash, plain Base64',
    ($session['request']['offlineMode'] ?? null) === true && ($session['request']['hashOfCorrectedInvoice'] ?? '') === $issuedRow->ksef_offline_hash
    && ($session['xml'] ?? '') === $corrected->ksef_xml && $wpdb->rows[52]->ksef_status === 'accepted');

// Duplicate of our own earlier send keeps KOD I for an offline invoice.
$newInvoice(53);
$service->issueOffline(53, 'offline24');
$wpdb->rows[53]->ksef_session = 'SESSION-0';
$qr53 = $wpdb->rows[53]->ksef_qr_url;
$answer($duplicate('SESSION-0'));
$service->processSubmission(53, 0, 'test');
$newInvoice(59);
$service->issueOffline(59, 'offline24');
$answer($duplicate('SESSION-0'));
$service->processSubmission(59, 0, 'test');
check('a 440 duplicate of someone else is rejected, keeps the codes, and is not technically correctable', $wpdb->rows[59]->ksef_status === 'rejected'
    && str_starts_with((string) $wpdb->rows[59]->ksef_error, '(440)') && $wpdb->rows[59]->ksef_cert_url !== null
    && $service->technicalCorrection(59)['code'] === 409);
$answer($duplicate('SESSION-0'));
check('440 of our own earlier send: accepted and KOD I kept, the bytes are the frozen ones', $wpdb->rows[53]->ksef_status === 'accepted' && $wpdb->rows[53]->ksef_qr_url === $qr53);

// KSeF down: an offline invoice is never given up on.
$fake->on('POST', '/sessions/online', [503, '{"title":"Service Unavailable","detail":"Przerwa techniczna"}']);
$newInvoice(54);
$service->issueOffline(54, 'offline24');
$GLOBALS['scheduled'] = [];
$service->processSubmission(54, 80, 'test');
check('past the two days of retries an offline invoice is still tried hourly, not failed', $wpdb->rows[54]->ksef_status !== 'failed'
    && str_starts_with((string) $wpdb->rows[54]->ksef_status, 'offline') && ($GLOBALS['scheduled'][0][2] ?? 0) >= 3599
    && ($GLOBALS['scheduled'][0][1]['attempt'] ?? 0) === 81);
$wpdb->rows[54]->ksef_offline_deadline = '2026-01-05 22:59:59';
$service->processSubmission(54, 81, 'test');
check('past its deadline it is offline_overdue and still requeued', $wpdb->rows[54]->ksef_status === 'offline_overdue' && count($GLOBALS['scheduled']) === 2);
$newInvoice(55);
$GLOBALS['scheduled'] = [];
$GLOBALS['options'][KSeFSyncService::OUTAGE_HINT_OPTION] = null;
unset($GLOBALS['options'][KSeFSyncService::OUTAGE_HINT_OPTION]);
$service->processSubmission(55, 3, 'test');
check('an online invoice out of quick retries leaves the hint to switch the outage mode on', isset($GLOBALS['options'][KSeFSyncService::OUTAGE_HINT_OPTION]) && $wpdb->rows[55]->ksef_status === 'queued');
$fake->on('POST', '/sessions/online', $openSession);

// Corrections wait for an offline original as long as it takes.
$answer($accepted);
$newInvoice(156, ['ksef_status' => 'offline', 'ksef_reference' => '', 'ksef_offline_mode' => 'offline24', 'ksef_environment' => 'test']);
$correctionOf(56, 156);
$GLOBALS['scheduled'] = [];
$before = count($fake->log);
$service->processSubmission(56, 0, 'test', 29);
check('a correction of an offline original is held past the one hour limit, nothing sent', ! $fake->sent($before) && $wpdb->rows[56]->ksef_status === 'queued'
    && ($GLOBALS['scheduled'][0][1]['hold'] ?? 0) === 30 && ($GLOBALS['scheduled'][0][2] ?? 0) >= 3599);
check('and it cannot be issued offline before the original has its number', $service->issueOffline(56, 'offline24')['code'] === 425);
$wpdb->rows[156]->ksef_status = 'accepted';
$wpdb->rows[156]->ksef_reference = $ksefNumber;
$wpdb->rows[56]->ksef_status = null;
check('once the original is numbered the correction is issued offline citing it', $service->issueOffline(56, 'offline24')['code'] === 202
    && str_contains((string) $wpdb->rows[56]->ksef_xml, '<NrKSeFFaKorygowanej>' . $ksefNumber . '</NrKSeFFaKorygowanej>'));

// Frozen bytes never go to another register.
$newInvoice(57);
$service->issueOffline(57, 'offline24');
$before = count($fake->log);
$production->processSubmission(57, 0, 'production');
check('an offline invoice issued for TEST is not sent to production', ! $fake->sent($before) && str_contains((string) $wpdb->rows[57]->ksef_error, 'QR codes point there'));

$newInvoice(58);
$service->issueOffline(58, 'offline24');
$wpdb->rows[58]->ksef_xml = str_replace('<P_2>', ' <P_2>', (string) $wpdb->rows[58]->ksef_xml);
$before = count($fake->log);
$service->processSubmission(58, 0, 'test');
check('frozen bytes that no longer match the printed KOD I are not sent', ! $fake->sent($before) && $wpdb->rows[58]->ksef_status === 'failed');

// Technical correction: rejected only, and never with changed content.
$newInvoice(61);
$service->issueOffline(61, 'offline24');
$wpdb->rows[61]->ksef_status = 'failed';
check('a failed offline invoice gets no technical correction (it is simply sent again)', $service->technicalCorrection(61)['code'] === 409);
$wpdb->rows[61]->ksef_status = 'rejected';
$wpdb->rows[61]->buyer_name = 'Inny Nabywca';
sleep(1);
$changed = $service->technicalCorrection(61);
check('a technical correction is refused when the rebuilt content differs', $changed['code'] === 409 && str_contains($changed['message'], 'content'));
$wpdb->rows[61]->buyer_name = 'Jan Kowalski';
check('with the same content it goes', $service->technicalCorrection(61)['code'] === 202);

// A correction held for an offline original goes offline itself once the original is numbered.
$GLOBALS['options']['polski_pro_ksef'] = ['enabled' => true, 'environment' => 'test', 'offline_mode' => 'offline24'];
$newInvoice(162, ['ksef_status' => 'accepted', 'ksef_reference' => $ksefNumber, 'ksef_offline_mode' => 'offline24', 'ksef_environment' => 'test']);
$correctionOf(62, 162);
$GLOBALS['scheduled'] = [];
$before = count($fake->log);
$service->processSubmission(62, 0, 'test', 3);
check('a released correction in offline24 is issued offline, not sent online', ! $fake->sent($before) && ! empty($wpdb->rows[62]->ksef_xml)
    && str_contains((string) $wpdb->rows[62]->ksef_cert_url, '/certificate/Nip/') && str_starts_with((string) $wpdb->rows[62]->ksef_status, 'offline')
    && ($GLOBALS['scheduled'][0][1]['invoice_id'] ?? 0) === 62);
$service->processSubmission(62, 0, 'test');
check('and its frozen bytes then go with offlineMode', $fake->sent($before) && ($session['request']['offlineMode'] ?? null) === true && ($session['xml'] ?? '') === $wpdb->rows[62]->ksef_xml);

$warsaw = new DateTimeZone('Europe/Warsaw');
$holidays = static fn (int $y): array => [];
check('deadlineFor: offline24 alone is the next working day', KSeFSyncService::deadlineFor('offline24', new DateTimeImmutable('2025-07-08', $warsaw), [], $holidays)?->format('Y-m-d') === '2025-07-09');
check('deadlineFor: a failure announced in the window moves it 7 working days past the failure end', KSeFSyncService::deadlineFor('offline24', new DateTimeImmutable('2025-07-08', $warsaw),
    ['outage_kind' => 'awaryjny', 'outage_start' => '2025-07-09T08:00', 'outage_end' => '2025-07-12T10:00'], $holidays)?->format('Y-m-d') === '2025-07-22');
check('deadlineFor: a failure announced after the deadline passed changes nothing', KSeFSyncService::deadlineFor('offline24', new DateTimeImmutable('2025-07-08', $warsaw),
    ['outage_kind' => 'awaryjny', 'outage_start' => '2025-07-15T08:00', 'outage_end' => '2025-07-16T10:00'], $holidays)?->format('Y-m-d') === '2025-07-09');
check('deadlineFor: a failure with no announced start does not reopen an offline24 window', KSeFSyncService::deadlineFor('offline24', new DateTimeImmutable('2025-07-08', $warsaw),
    ['outage_kind' => 'awaryjny', 'outage_end' => '2025-07-16T10:00'], $holidays)?->format('Y-m-d') === '2025-07-09');
check('deadlineFor: unavailability with an unknown end has no deadline', KSeFSyncService::deadlineFor('offline', new DateTimeImmutable('2025-07-08', $warsaw), ['outage_kind' => 'offline'], $holidays) === null);
check('deadlineFor: unavailability ending on Friday gives Monday', KSeFSyncService::deadlineFor('offline', new DateTimeImmutable('2025-07-08', $warsaw),
    ['outage_kind' => 'offline', 'outage_end' => '2025-07-11T18:00'], $holidays)?->format('Y-m-d') === '2025-07-14');

// --- Statistic ----------------------------------------------------------------------

$wpdb->queries = [];
(new InvoiceRepository($wpdb))->getStats(30);
$sentQuery = implode("\n", array_filter($wpdb->queries, static fn (string $q): bool => str_contains($q, 'ksef')));
check('ksef_sent counts accepted invoices, not any non-null reference', str_contains($sentQuery, "ksef_status = 'accepted'")
    && ! str_contains($sentQuery, 'ksef_reference IS NOT NULL'));

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures > 0 ? 1 : 0);
