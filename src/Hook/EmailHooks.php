<?php

declare(strict_types=1);

namespace Spolszczony\Hook;

use Spolszczony\Contract\HasHooks;
use Spolszczony\Gateway\InvoiceGateway;
use Spolszczony\Service\EmailService;

final class EmailHooks implements HasHooks
{
    public function __construct(
        private readonly EmailService $emailService,
    ) {
    }

    public function registerHooks(): void
    {
        // Register payment gateway.
        add_filter('woocommerce_payment_gateways', [$this, 'registerGateways']);
    }

    /**
     * @param list<string> $gateways
     * @return list<string>
     */
    public function registerGateways(array $gateways): array
    {
        $gateways[] = InvoiceGateway::class;
        return $gateways;
    }
}
