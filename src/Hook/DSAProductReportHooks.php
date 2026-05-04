<?php

declare(strict_types=1);

namespace Polski\Hook;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Service\DSAProductReportService;

/**
 * Wires the per-product DSA widget into the WooCommerce single-product
 * template. Position is configurable via polski_dsa.product_widget_position.
 */
final class DSAProductReportHooks implements HasHooks
{
    public function __construct(private readonly DSAProductReportService $service)
    {
    }

    public function registerHooks(): void
    {
        add_action('wp', [$this, 'attachToProductTemplate']);
    }

    /**
     * Late hook attachment so we know the chosen position before binding to
     * the template hook (admins may switch position without a reload).
     */
    public function attachToProductTemplate(): void
    {
        if (! $this->service->isWidgetEnabled()) {
            return;
        }

        add_action($this->service->widgetHook(), [$this->service, 'render'], 25);
    }
}
