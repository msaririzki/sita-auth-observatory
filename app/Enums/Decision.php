<?php

namespace App\Enums;

enum Decision: string
{
    case Allow = 'allow';
    case Deny = 'deny';
}
