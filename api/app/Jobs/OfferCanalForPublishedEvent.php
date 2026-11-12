<?php

namespace App\Jobs;

use App\Services\Canals\CanalEventOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Zverejnená akcia nespravovaného kanála → technický e-mail jeho kontaktu.
 *
 * Cez frontu a až po commite: import zakladá akciu skôr, než kanálu doplní
 * e-mail a zverejní jeho profil — o tom, či a komu písať, rozhoduje stav
 * po dokončení importu, nie okamih vzniku akcie.
 */
class OfferCanalForPublishedEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $eventId) {}

    public function handle(CanalEventOffer $offer): void
    {
        $offer->notify($this->eventId);
    }
}
