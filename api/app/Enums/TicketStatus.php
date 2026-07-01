<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Reserved  = 'reserved';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('tickets.status.' . $this->value);
    }
}
