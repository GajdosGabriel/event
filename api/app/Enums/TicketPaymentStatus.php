<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;

enum TicketPaymentStatus: string implements HasLabel
{
    use ProvidesOptions;

    case None     = 'none';
    case Pending  = 'pending';
    case Paid     = 'paid';
    case Failed   = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return __('tickets.payment_status.' . $this->value);
    }
}
