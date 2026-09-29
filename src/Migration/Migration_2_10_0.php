<?php

declare(strict_types=1);

namespace Polski\Migration;

use Polski\Contract\Migration;

defined('ABSPATH') || exit;

/**
 * Seed the default units and delivery times again.
 *
 * Migration 1.0.0 ran before the taxonomies were registered, so on most sites
 * it was marked executed without creating a single term and the Unit and
 * Delivery time dropdowns stayed empty. The seed skips terms that exist.
 */
final class Migration_2_10_0 implements Migration
{
    public const VERSION = '2.10.0';

    public function run(): void
    {
        (new Migration_1_0_0())->run();
    }
}
