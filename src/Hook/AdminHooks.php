<?php

declare(strict_types=1);

namespace Spolszczony\Hook;

use Spolszczony\Admin\AdminPage;
use Spolszczony\Contract\HasHooks;

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
