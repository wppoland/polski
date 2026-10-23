<?php

declare(strict_types=1);

namespace Polski\Migration;

use Polski\Contract\Migration;
use Polski\Service\IworksOmnibusImport;

defined('ABSPATH') || exit;

/**
 * Runs the iworks import again with the corrected mapping. It removes the rows
 * 1.41.7 imported from the raw sale field or into the period Polski already
 * covered, which could make the notice show the running sale price.
 */
final class Migration_2_12_0 implements Migration
{
    public const VERSION = '2.12.0';

    public function run(): void
    {
        (new IworksOmnibusImport())->run();
    }
}
