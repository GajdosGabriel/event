<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;

enum TicketTypeKind: string implements HasLabel
{
    use ProvidesOptions;

    case Ticket   = 'ticket';
    case Workshop = 'workshop';

    public function label(): string
    {
        return __('tickets.type_kind.' . $this->value);
    }
}
