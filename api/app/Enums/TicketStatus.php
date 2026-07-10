<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;

enum TicketStatus: string implements HasLabel
{
    use ProvidesOptions;

    case Reserved  = 'reserved';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('tickets.status.' . $this->value);
    }
}
