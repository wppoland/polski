<?php

declare(strict_types=1);

namespace Spolszczony\Service;

use Spolszczony\Contract\Bootable;
use Spolszczony\Contract\HasHooks;
use Spolszczony\Repository\OmnibusPriceRepository;

/**
 * Built-in Omnibus directive compliance (30-day lowest price tracking).
 *
 * Used as a fallback when no external Omnibus plugin is detected.
 */
final class OmnibusService implements Bootable, HasHooks
{
    public function __construct(
        private readonly OmnibusPriceRepository $repository,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        // Will hook into product price changes in Phase 2.
        add_action('spolszczony_daily_maintenance', [$this, 'pruneOldRecords']);
    }

    public function pruneOldRecords(): void
    {
        $settings = get_option('spolszczony_omnibus', []);
        $days = (int) ($settings['prune_after_days'] ?? 90);
        $this->repository->deleteOlderThan($days);
    }
}
