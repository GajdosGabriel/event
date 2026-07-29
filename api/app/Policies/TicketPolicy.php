<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyCanalAbility('ticket.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->canInCanal((int) $ticket->event->canal_id, 'ticket.view');
    }

    /**
     * Determine whether the user can update (cancel) the model.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->canInCanal((int) $ticket->event->canal_id, 'ticket.update');
    }

    /**
     * Determine whether the user can check in the ticket at the door.
     */
    public function checkin(User $user, Ticket $ticket): bool
    {
        return $user->canInCanal((int) $ticket->event->canal_id, 'ticket.checkin');
    }
}
