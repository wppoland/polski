<?php

declare(strict_types=1);

namespace Spolszczony\Service;

final class PriceDisplayService
{
    public function __construct(
        private readonly TaxDisplayService $taxDisplay,
        private readonly OmnibusService $omnibus,
    ) {
    }

    // Placeholder — will be fully implemented in Phase 2.
}
