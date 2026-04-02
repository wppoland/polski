<?php

declare(strict_types=1);

namespace Spolszczony\Service;

use Spolszczony\Enum\TaxDisplayMode;

final class TaxDisplayService
{
    public function getMode(): TaxDisplayMode
    {
        $settings = get_option('spolszczony_taxes', []);
        // Placeholder — will be fully implemented in Phase 2.
        return TaxDisplayMode::Brutto;
    }

    public function isSmallBusiness(): bool
    {
        $settings = get_option('spolszczony_general', []);
        return (bool) ($settings['small_business'] ?? false);
    }

    public function getVatNotice(\WC_Product $product): string
    {
        if ($this->isSmallBusiness()) {
            $settings = get_option('spolszczony_taxes', []);
            return $settings['vat_exempt_notice'] ?? '';
        }

        return '';
    }
}
