<?php

namespace App\Console\Commands;

use App\Services\Tickets\AttendeeConfirmation;
use Illuminate\Console\Command;

class ExpireUnconfirmedAdmissions extends Command
{
    protected $signature = 'app:tickets-expire-unconfirmed';

    protected $description = 'Cancel attendee reservations that were not confirmed before their deadline';

    public function handle(AttendeeConfirmation $confirmation): int
    {
        $released = $confirmation->expirePending();

        $this->info("Released unconfirmed seats: {$released}");

        return self::SUCCESS;
    }
}
