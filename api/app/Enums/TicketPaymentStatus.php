<?php

namespace App\Enums;

enum TicketPaymentStatus: string
{
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
