<?php

declare(strict_types=1);
namespace Polski\Enum;

defined('ABSPATH') || exit;
enum ConsentType: string
{
    case Required = 'required';
    case Optional = 'optional';
}
