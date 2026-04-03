<?php

declare(strict_types=1);

namespace Polski\Hook;

use Polski\Contract\HasHooks;
use Polski\Util\TemplateLoader;

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
