<?php

namespace App\Enums;

enum FileType: string
{
    case IMAGE = 'image';
    case CARD  = 'card';
    case FILE  = 'file';
}
