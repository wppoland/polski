<?php

declare(strict_types=1);

namespace Spolszczony\Contract;

/**
 * Extension point for shipping provider integrations.
 */
interface ShippingProvider
{
    public function id(): string;

    public function name(): string;

    public function isAvailable(): bool;

    /**
     * @return array<string, mixed>
     */
    public function getTrackingData(string $trackingNumber): array;
}
