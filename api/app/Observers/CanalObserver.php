<?php

namespace App\Observers;

use App\Models\Canal;

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
