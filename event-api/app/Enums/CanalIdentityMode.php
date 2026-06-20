<?php

namespace App\Enums;

enum CanalIdentityMode: string
{
    case Personal = 'personal';
    case Organization = 'organization';
    case Pseudonymous = 'pseudonymous';
}
