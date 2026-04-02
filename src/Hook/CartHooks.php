<?php

declare(strict_types=1);

namespace Spolszczony\Hook;

use Spolszczony\Contract\HasHooks;
use Spolszczony\Util\TemplateLoader;

final class CartHooks implements HasHooks
{
    public function __construct(
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function registerHooks(): void
    {
        // Cart hooks will be implemented in Phase 2.
    }
}
