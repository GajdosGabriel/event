<?php

namespace App\Observers;

use App\Jobs\OfferCanalAfterContactChange;
use App\Models\Canal;
use App\Models\CanalEmailSearch;
use App\Services\Canals\CanalEmails;
use App\Support\Url;

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

        // Nový kontakt → ponuka prevzatia k akcii, ku ktorej e-mail ešte neodišiel.
        // Pri vložení je `wasChanged()` prázdne (nový kanál ešte akcie nemá, tie
        // si e-mail pošlú pri zverejnení); `wasRecentlyCreated` tu nepomôže —
        // ostáva true aj pri ďalšom uložení tej istej inštancie, napr. v importe.
        if ($canal->wasChanged('email') && filled($canal->email)) {
            OfferCanalAfterContactChange::dispatch((int) $canal->id)->afterCommit();
        }

        // Nový web je nový zdroj kontaktu — hľadanie adresy nečaká na odklad.
        if ($canal->wasChanged('website') && Url::normalize($canal->website) !== null) {
            CanalEmailSearch::retryFor($canal);
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
