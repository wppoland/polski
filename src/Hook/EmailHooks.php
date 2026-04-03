<?php

declare(strict_types=1);

namespace Polski\Hook;

use Polski\Contract\HasHooks;
use Polski\Gateway\InvoiceGateway;
use Polski\Service\EmailService;

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
