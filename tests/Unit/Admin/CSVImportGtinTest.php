<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Polski\Admin\CSVImportExport;

final class CSVImportGtinTest extends TestCase
{
    public function testInvalidGtinIsPreservedAndWarnedDuringImport(): void
    {
        if (! class_exists('WC_Product_Simple')) {
            self::markTestSkipped('WooCommerce product classes are not loaded in this unit test environment.');
        }

        $product = new \WC_Product_Simple();
        $invalid = '5901234567890';
        $warnings = [];
        $listener = static function (string $value, \WC_Product $importedProduct) use (&$warnings): void {
            $warnings[] = [$value, $importedProduct];
        };
        add_action('polski/gtin/csv_import_warning', $listener, 10, 2);

        try {
            $result = (new CSVImportExport())->processImport($product, ['polski_gtin' => $invalid]);
        } finally {
            remove_action('polski/gtin/csv_import_warning', $listener, 10);
        }

        self::assertSame($invalid, $result->get_meta('_polski_gtin', true));
        self::assertCount(1, $warnings);
        self::assertSame($invalid, $warnings[0][0]);
        self::assertSame($result, $warnings[0][1]);
    }
}
