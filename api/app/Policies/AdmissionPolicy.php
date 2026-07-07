<?php

namespace App\Policies;

use App\Models\Admission;
use App\Models\User;

class AdmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->dashboardCanalIds()->isNotEmpty();
    }

    public function view(User $user, Admission $admission): bool
    {
        return $user->dashboardCanalIds()->contains((int) $admission->event->canal_id);
    }

    public function update(User $user, Admission $admission): bool
    {
        return $user->dashboardCanalIds()->contains((int) $admission->event->canal_id);
    }

    /** Check-in vstupenky pri vchode. */
    public function checkin(User $user, Admission $admission): bool
    {
        return $user->dashboardCanalIds()->contains((int) $admission->event->canal_id);
    }
}
