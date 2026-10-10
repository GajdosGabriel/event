<?php

namespace App\Observers;

use App\Models\Canal;
use App\Services\Canals\CanalEmails;

class CanalObserver
{
    /**
     * Handle the Canal "created" event.
     */
    public function created(Canal $canal): void
    {
        //
    }

    /**
     * Handle the Canal "updated" event.
     */
    public function updated(Canal $canal): void
    {
        //
    }

    /**
     * `email` môže zapísať ktokoľvek (formulár, import, migrácia) — zoznam
     * adries kanála sa s ním musí zhodnúť. Viď CanalEmails::adopt().
     */
    public function saved(Canal $canal): void
    {
        if ($canal->wasRecentlyCreated || $canal->wasChanged(['email', 'email_verified_at'])) {
            app(CanalEmails::class)->adopt($canal);
        }
    }

    /**
     * Handle the Canal "deleted" event.
     */
    public function deleted(Canal $canal): void
    {
        //
    }

    /**
     * Handle the Canal "restored" event.
     */
    public function restored(Canal $canal): void
    {
        //
    }

    /**
     * Handle the Canal "force deleted" event.
     */
    public function forceDeleted(Canal $canal): void
    {
        //
    }
}
