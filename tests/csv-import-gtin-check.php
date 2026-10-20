<?php
/**
 * Checks that invalid GTINs from CSV are kept and reported without a lookup service.
 *
 * Run: php tests/csv-import-gtin-check.php
 *
 * @package Polski
 */

define('ABSPATH', __DIR__);

class WC_Product
{
    /** @var array<string, mixed> */
    private array $metadata = [];

    public function get_id(): int
    {
        return 42;
    }

    public function update_meta_data(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function get_meta(string $key, bool $single = true): mixed
    {
        return $this->metadata[$key] ?? '';
    }
}

final class GtinCheckLogger
{
    /** @var list<array{string, array<string, mixed>}> */
    public array $warnings = [];

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->warnings[] = [$message, $context];
    }
}

$gtinLogger = new GtinCheckLogger();
$gtinActions = [];

function sanitize_text_field(string $value): string
{
    return trim(strip_tags($value));
}

function sanitize_textarea_field(string $value): string
{
    return trim(strip_tags($value));
}

function wc_get_logger(): GtinCheckLogger
{
    global $gtinLogger;
    return $gtinLogger;
}

function do_action(string $hook, mixed ...$args): void
{
    global $gtinActions;
    if ($hook === 'polski/gtin/csv_import_warning') {
        $gtinActions[] = $args;
    }
}

require_once dirname(__DIR__) . '/src/Contract/HasHooks.php';
require_once dirname(__DIR__) . '/src/Util/GtinValidator.php';
require_once dirname(__DIR__) . '/src/Admin/CSVImportExport.php';

$invalid = '5901234567890';
$product = new WC_Product();
$result = (new Polski\Admin\CSVImportExport())->processImport($product, ['polski_gtin' => $invalid]);

if ($result->get_meta('_polski_gtin') !== $invalid || count($gtinActions) !== 1 || count($gtinLogger->warnings) !== 1) {
    fwrite(STDERR, "FAIL: invalid GTIN was not preserved and reported.\n");
    exit(1);
}

if ($gtinActions[0][0] !== $invalid || $gtinLogger->warnings[0][1]['source'] !== 'polski-gtin') {
    fwrite(STDERR, "FAIL: warning did not identify the imported GTIN.\n");
    exit(1);
}

printf("csv-import-gtin-check: OK (invalid GTIN preserved, action and log warning emitted)\n");
