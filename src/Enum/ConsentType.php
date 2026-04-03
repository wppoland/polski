<?php

declare(strict_types=1);

namespace Polski\Enum;

enum ConsentType: string
{
    case Required = 'required';
    case Optional = 'optional';
}
