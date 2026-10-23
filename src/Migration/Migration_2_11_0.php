<?php

declare(strict_types=1);

namespace Polski\Migration;

use Polski\Contract\Migration;
use Polski\Service\IworksOmnibusImport;

defined('ABSPATH') || exit;

/**
 * Imports the price history of the "Omnibus" plugin (iworks), so a shop that
 * moves from it to Polski keeps its 30-day window. A no-op where that plugin
 * never ran. Rerun by hand with `wp polski import-iworks`.
 */
final class Migration_2_11_0 implements Migration
{
    public const VERSION = '2.11.0';

    public function run(): void
    {
        (new IworksOmnibusImport())->run();
    }
}
