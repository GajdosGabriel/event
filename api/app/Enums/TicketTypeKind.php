<?php

namespace App\Enums;

enum TicketTypeKind: string
{
    case Ticket   = 'ticket';
    case Workshop = 'workshop';

    public function label(): string
    {
        return __('tickets.type_kind.' . $this->value);
    }
}
