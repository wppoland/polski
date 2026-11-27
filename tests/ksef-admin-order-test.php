<?php

declare(strict_types=1);

/**
 * KSeF on the order screens, without WordPress.
 *
 *   php tests/ksef-admin-order-test.php
 *
 * Covers the status badge mapping, the one-query prefetch behind the orders
 * list column, who may press the metabox buttons (capability, then a nonce
 * bound to action, invoice and refund), that a refused request never reaches
 * the sync service, when auto_send queues, and the UPO file name.
 */

define('ABSPATH', __DIR__);

$root = dirname(__DIR__) . '/';

// --- WordPress, as little of it as the code under test touches ---------------

$GLOBALS['can'] = true;
$GLOBALS['nonces'] = [];

function __(string $t, string $d = ''): string { return $t; }
function esc_html(string $t): string { return htmlspecialchars($t, ENT_QUOTES); }
function esc_attr(string $t): string { return htmlspecialchars($t, ENT_QUOTES); }
function esc_html__(string $t, string $d = ''): string { return esc_html($t); }
function esc_attr__(string $t, string $d = ''): string { return esc_attr($t); }
function current_user_can(string $cap): bool { return $GLOBALS['can'] && $cap === 'manage_woocommerce'; }
function wp_verify_nonce(string $nonce, string $action): int|false { return ($GLOBALS['nonces'][$nonce] ?? null) === $action ? 1 : false; }
function absint(mixed $v): int { return abs((int) $v); }
function sanitize_text_field(string $v): string { return trim($v); }
function wp_unslash(string $v): string { return $v; }
function add_action(string $h, mixed $c, int $p = 10, int $a = 1): void {}
function add_filter(string $h, mixed $c, int $p = 10, int $a = 1): void {}
final class WpDie extends Exception {}
function wp_die(string $message = '', string $title = '', array $args = []): never { throw new WpDie($message, (int) ($args['response'] ?? 500)); }

eval('namespace Polski\Contract; interface HasHooks { public function registerHooks(): void; }');

final class WC_Order
{
    public function __construct(private int $id) {}
    public function get_id(): int { return $this->id; }
}

final class wpdb
{
    public string $prefix = 'wp_';
    /** @var list<string> */
    public array $queries = [];
    /** @var list<object> */
    public array $rows = [];

    public function prepare(string $sql, mixed ...$args): string
    {
        $sql = str_replace('%i', '`' . array_shift($args) . '`', $sql);
        return vsprintf(str_replace('%d', '%d', $sql), $args);
    }

    /** @return list<object> */
    public function get_results(string $sql): array
    {
        $this->queries[] = $sql;
        preg_match('/IN \(([\d,]+)\)/', $sql, $m);
        $ids = array_map('intval', explode(',', $m[1] ?? ''));
        $rows = array_values(array_filter($this->rows, static fn (object $r): bool => in_array((int) $r->order_id, $ids, true)));
        usort($rows, static fn (object $a, object $b): int => $b->id <=> $a->id);
        return $rows;
    }
}

foreach ([
    'src/KSeF/Model/InvoiceStatus.php',
    'src/KSeF/Model/InvoiceType.php',
    'src/KSeF/Model/Invoice.php',
    'src/KSeF/Repository/InvoiceRepository.php',
    'src/KSeF/Admin/KSeFOrderColumn.php',
    'src/KSeF/Admin/KSeFOrderBox.php',
] as $file) {
    require $root . $file;
}

use Polski\KSeF\Admin\KSeFOrderBox;
use Polski\KSeF\Admin\KSeFOrderColumn;
use Polski\KSeF\Model\Invoice;
use Polski\KSeF\Model\InvoiceStatus;
use Polski\KSeF\Model\InvoiceType;
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

function invoice(string $ksefReference = '', string $number = 'FV/1/2026'): Invoice
{
    $now = new DateTimeImmutable('2026-09-26');
    return new Invoice(7, 3, null, null, InvoiceType::FakturaVAT, $number, InvoiceStatus::Issued, '9999999999', null, null, 100.0, 23.0, 123.0, 'PLN', null, $ksefReference, null, $now, $now);
}

// --- Badge mapping -----------------------------------------------------------

$tones = [];
foreach (['', 'queued', 'submitted', 'accepted', 'rejected', 'failed', 'no_nip'] as $s) {
    $tones[$s] = KSeFOrderColumn::badge($s)['tone'];
}
check('never sent is neutral', $tones[''] === 'neutral');
check('queued and processing are in progress', $tones['queued'] === 'info' && $tones['submitted'] === 'info');
check('accepted is success', $tones['accepted'] === 'success');
check('rejected and failed are errors', $tones['rejected'] === 'error' && $tones['failed'] === 'error');
check('a missing seller NIP is a warning', $tones['no_nip'] === 'warning');
check('every known status has its own label', count(array_unique(array_map(static fn (string $s): string => KSeFOrderColumn::badge($s)['label'], array_keys($tones)))) === count($tones));
check('an unknown status shows readably, not blank', KSeFOrderColumn::badge('some_state')['label'] === 'Some state');

$html = KSeFOrderColumn::badgeHtml('failed', 'test', 'KSeF said <no> & "why"');
check('the last error replaces the generic tooltip, escaped', str_contains($html, 'data-tip="KSeF said &lt;no&gt; &amp; &quot;why&quot;"'));
check('a TEST invoice carries the TEST marker', str_contains($html, 'polski-ksef-env'));
check('a production invoice carries no TEST marker', ! str_contains(KSeFOrderColumn::badgeHtml('accepted', 'production'), 'polski-ksef-env'));

// --- Orders list column: one query per page ----------------------------------

$wpdb = new wpdb();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->rows = [
    (object) ['id' => 1, 'order_id' => 10, 'ksef_status' => 'accepted', 'ksef_environment' => 'production', 'ksef_error' => null],
    (object) ['id' => 5, 'order_id' => 10, 'ksef_status' => 'queued', 'ksef_environment' => 'production', 'ksef_error' => null],
    (object) ['id' => 2, 'order_id' => 11, 'ksef_status' => 'rejected', 'ksef_environment' => 'test', 'ksef_error' => 'Bad NIP'],
    (object) ['id' => 7, 'order_id' => 13, 'ksef_status' => 'accepted', 'ksef_environment' => 'production', 'ksef_error' => null],
    (object) ['id' => 3, 'order_id' => 13, 'ksef_status' => 'rejected', 'ksef_environment' => 'production', 'ksef_error' => 'Original refused'],
];
$column = new KSeFOrderColumn(new InvoiceRepository($wpdb));
$column->collectHpos((object) ['orders' => [new WC_Order(10), new WC_Order(11), new WC_Order(12), new WC_Order(13)]]);

$out = [];
foreach ([10, 11, 12, 13] as $id) {
    ob_start();
    $column->renderHpos('polski_ksef', new WC_Order($id));
    $out[$id] = (string) ob_get_clean();
}
ob_start();
$column->renderHpos('order_total', new WC_Order(10));
$other = (string) ob_get_clean();

check('four rows cost one query', count($wpdb->queries) === 1);
check('the query asks for all three orders at once', str_contains($wpdb->queries[0] ?? '', 'IN (10,11,12,13)'));
check('only documents KSeF takes are read', str_contains($wpdb->queries[0] ?? '', "type IN ('faktura_vat', 'korygujaca')"));
check('a pending correction outranks the accepted original', str_contains($out[10], 'Queued'));
check('an accepted correction does not hide a rejected original', str_contains($out[13], 'Rejected') && str_contains($out[13], 'Original refused'));
check('a rejected invoice shows its error as the tooltip', str_contains($out[11], 'Rejected') && str_contains($out[11], 'Bad NIP'));
check('an order without an invoice shows a dash', str_contains($out[12], 'No invoice') && ! str_contains($out[12], 'polski-ksef-badge'));
check('other columns are left alone', $other === '');

$cols = $column->addColumn(['cb' => '', 'order_status' => 'Status', 'order_total' => 'Total']);
check('the column sits right after Status', array_keys($cols) === ['cb', 'order_status', 'polski_ksef', 'order_total']);

$single = new KSeFOrderColumn(new InvoiceRepository($wpdb));
ob_start();
$single->renderHpos('polski_ksef', new WC_Order(11));
ob_end_clean();
check('an order that was not prefetched still gets its badge, alone', str_ends_with($wpdb->queries[1] ?? '', 'IN (11) AND type IN (\'faktura_vat\', \'korygujaca\') ORDER BY id DESC'));

// --- Who may press the buttons -----------------------------------------------

$GLOBALS['nonces'] = [
    'n-send-7' => KSeFOrderBox::nonceAction('send', 7),
    'n-corr-7-40' => KSeFOrderBox::nonceAction('correction', 7, 40),
    'n-upo-7' => KSeFOrderBox::nonceAction('upo', 7),
];

$GLOBALS['can'] = true;
check('a shop manager with the right nonce may send', KSeFOrderBox::authorized('send', 7, 'n-send-7'));
$GLOBALS['can'] = false;
check('without manage_woocommerce the nonce does not help', ! KSeFOrderBox::authorized('send', 7, 'n-send-7'));
$GLOBALS['can'] = true;
check('a missing nonce is refused', ! KSeFOrderBox::authorized('send', 7, ''));
check('a nonce for another invoice is refused', ! KSeFOrderBox::authorized('send', 8, 'n-send-7'));
check('a send nonce does not open the UPO download', ! KSeFOrderBox::authorized('upo', 7, 'n-send-7'));
check('an unknown action is refused', ! KSeFOrderBox::authorized('delete', 7, 'n-send-7'));
check('a correction nonce is bound to its refund', KSeFOrderBox::authorized('correction', 7, 'n-corr-7-40', 40) && ! KSeFOrderBox::authorized('correction', 7, 'n-corr-7-40', 41));

// A refused request dies with 403 before any service is touched: the box has
// no services at all here, so reaching one would throw an Error, not WpDie.
$box = (new ReflectionClass(KSeFOrderBox::class))->newInstanceWithoutConstructor();
// Without the capability the nonce is the right one, so only the capability refuses.
$valid = ['handleSend' => 'n-send-7', 'handleRefresh' => '', 'handleUpo' => 'n-upo-7', 'handleCorrection' => 'n-corr-7-40'];
foreach (['handleSend' => true, 'handleRefresh' => false, 'handleUpo' => true, 'handleCorrection' => true, 'handleSend ' => false] as $handler => $dropCap) {
    $handler = trim($handler);
    $GLOBALS['can'] = ! $dropCap;
    $_GET = ['invoice_id' => '7', '_wpnonce' => $dropCap ? $valid[$handler] : 'forged', 'refund_id' => '40'];
    $code = 0;
    try {
        $box->{$handler}();
    } catch (WpDie $e) {
        $code = $e->getCode();
    } catch (Error) {
        $code = -1;
    }
    check("{$handler} refuses " . ($dropCap ? 'a user without the capability' : 'a forged nonce') . ' with 403', $code === 403);
}
$GLOBALS['can'] = true;

// --- auto_send ---------------------------------------------------------------

$asked = 0;
$yes = static function () use (&$asked): bool { $asked++; return true; };
$no = static fn (): bool => false;
$on = ['enabled' => true, 'auto_send' => true];

check('enabled + auto_send + credentials queues a VAT invoice', KSeFOrderBox::shouldAutoSend($on, InvoiceType::FakturaVAT, $yes));
check('a correction is queued too', KSeFOrderBox::shouldAutoSend($on, InvoiceType::Korygujaca, $yes));
check('without credentials nothing is queued', ! KSeFOrderBox::shouldAutoSend($on, InvoiceType::FakturaVAT, $no));
$asked = 0;
check('auto_send off queues nothing', ! KSeFOrderBox::shouldAutoSend(['enabled' => true], InvoiceType::FakturaVAT, $yes));
check('KSeF switched off queues nothing', ! KSeFOrderBox::shouldAutoSend(['auto_send' => true], InvoiceType::FakturaVAT, $yes));
check('a proforma is never queued', ! KSeFOrderBox::shouldAutoSend($on, InvoiceType::Proforma, $yes));
check('the key is not decrypted when a setting already says no', $asked === 0);

// --- offline mode for a new invoice --------------------------------------------

$asked = 0;
$offline24 = ['enabled' => true, 'offline_mode' => 'offline24'];
check('offline24 issues a VAT invoice offline, even with auto_send off', KSeFOrderBox::offlineModeFor($offline24, InvoiceType::FakturaVAT, $yes) === 'offline24');
check('the outage switch wins over offline24', KSeFOrderBox::offlineModeFor($offline24 + ['outage_active' => true, 'outage_kind' => 'awaryjny'], InvoiceType::FakturaVAT, $yes) === 'awaryjny');
check('the outage switch works with the store on online', KSeFOrderBox::offlineModeFor(['enabled' => true, 'offline_mode' => 'online', 'outage_active' => true, 'outage_kind' => 'offline'], InvoiceType::FakturaVAT, $yes) === 'offline');
check('an outage kind with the switch off is ignored', KSeFOrderBox::offlineModeFor(['enabled' => true, 'outage_kind' => 'offline'], InvoiceType::FakturaVAT, $yes) === '');
check('without an Offline certificate the invoice stays online', KSeFOrderBox::offlineModeFor($offline24, InvoiceType::FakturaVAT, $no) === '');
$asked = 0;
check('KSeF switched off: never offline', KSeFOrderBox::offlineModeFor(['offline_mode' => 'offline24'], InvoiceType::FakturaVAT, $yes) === '');
check('a proforma is never offline', KSeFOrderBox::offlineModeFor($offline24, InvoiceType::Proforma, $yes) === '');
check('the Offline key is not decrypted when a setting already says no', $asked === 0);
check('offline badges exist and an overdue one is an error', KSeFOrderColumn::badge('offline')['tone'] === 'info' && KSeFOrderColumn::badge('offline_overdue')['tone'] === 'error');

// --- UPO file name ------------------------------------------------------------

check('the UPO is named after the KSeF number', KSeFOrderBox::upoFilename(invoice('9999999999-20260926-41D674000000-86')) === 'upo-9999999999-20260926-41D674000000-86.xml');
check('without a KSeF number the invoice number is made file-safe', KSeFOrderBox::upoFilename(invoice('', 'FV/12/09/2026')) === 'upo-FV-12-09-2026.xml');
check('nothing from the reference can break out of the header', KSeFOrderBox::upoFilename(invoice("a\"\r\nX: y")) === 'upo-a-X-y.xml');

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
