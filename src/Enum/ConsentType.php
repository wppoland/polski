<?php

declare(strict_types=1);

namespace Spolszczony\Enum;

enum ConsentType: string
{
    case Required = 'required';
    case Optional = 'optional';
}
