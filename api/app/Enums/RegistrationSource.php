<?php

namespace App\Enums;

enum RegistrationSource: string
{
    case SELF   = 'self';
    case ADMIN  = 'admin';
    case SYSTEM = 'system';
    case IMPORT = 'import';
}
