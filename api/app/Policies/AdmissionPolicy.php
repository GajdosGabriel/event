<?php

namespace App\Policies;

use App\Models\Admission;
use App\Models\User;

class AdmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyCanalAbility('ticket.view');
    }

    public function view(User $user, Admission $admission): bool
    {
        return $user->canInCanal((int) $admission->event->canal_id, 'ticket.view');
    }

    public function update(User $user, Admission $admission): bool
    {
        return $user->canInCanal((int) $admission->event->canal_id, 'ticket.update');
    }

    /** Check-in vstupenky pri vchode. */
    public function checkin(User $user, Admission $admission): bool
    {
        return $user->canInCanal((int) $admission->event->canal_id, 'ticket.checkin');
    }
}
