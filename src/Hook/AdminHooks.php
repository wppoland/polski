<?php

declare(strict_types=1);

namespace Polski\Hook;

use Polski\Admin\AdminPage;
use Polski\Contract\HasHooks;

final class AdminHooks implements HasHooks
{
    public function __construct(
        private readonly AdminPage $adminPage,
    ) {
    }

    public function registerHooks(): void
    {
        $this->adminPage->registerHooks();
    }
}
