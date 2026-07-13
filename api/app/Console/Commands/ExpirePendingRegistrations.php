<?php

namespace App\Console\Commands;

use App\Models\PendingRegistration;
use Illuminate\Console\Command;

class ExpirePendingRegistrations extends Command
{
    protected $signature = 'app:registrations-expire-pending';

    protected $description = 'Delete pending registrations whose verification token has expired';

    public function handle(): int
    {
        $deleted = PendingRegistration::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Deleted expired pending registrations: {$deleted}");

        return self::SUCCESS;
    }
}
