<?php

declare(strict_types=1);

namespace Spolszczony\Contract;

/**
 * Something that can be rendered to HTML.
 */
interface Renderable
{
    public function render(): string;
}
