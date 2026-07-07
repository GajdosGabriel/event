<?php

namespace App\Enums;

enum AdmissionStatus: string
{
    case Valid     = 'valid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('tickets.admission_status.' . $this->value);
    }
}
