<?php

namespace App\Jobs;

use App\Services\Canals\CanalEventOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Kanál alebo jeho akcia dostali kontaktný e-mail (alebo nový) → technický
 * e-mail s ponukou prevzatia k najbližšej akcii, ku ktorej ešte neodišiel.
 *
 * Import zakladá kanál a akciu skôr, než dohľadá e-mail; pri zverejnení akcie
 * (OfferCanalForPublishedEvent) tak často nebolo komu písať.
 */
class OfferCanalAfterContactChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $canalId) {}

    public function handle(CanalEventOffer $offer): void
    {
        $offer->notifyCanal($this->canalId);
    }
}
