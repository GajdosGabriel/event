<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;

enum AdmissionStatus: string implements HasLabel
{
    use ProvidesOptions;

    case Valid      = 'valid';
    case Waitlisted = 'waitlisted';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return __('tickets.admission_status.' . $this->value);
    }
}
