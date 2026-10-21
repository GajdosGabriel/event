<?php

namespace App\Jobs;

use App\Services\Account\AccountClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Vec sa zrušila – čakajúce maily k nej v Accounte sa neodošlú
 * (okrem kampaní na udalosti v `$except`, napr. práve zrušenie).
 *
 * Cez frontu rovnako ako ReportMailingEvent; opakovanie je neškodné.
 */
class CancelMailing implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600, 10800];

    /** @param  array<int, string>  $except */
    public function __construct(public string $refType, public string $refId, public array $except = []) {}

    public function handle(AccountClient $account): void
    {
        $account->cancelMailing($this->refType, $this->refId, $this->except);
    }
}
