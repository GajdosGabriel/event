<?php

namespace App\Observers;

use App\Http\Controllers\Public\MunicipalityController;
use App\Models\Municipality;
use Illuminate\Support\Facades\Cache;

/**
 * Drží verejný číselník obcí v súlade s databázou.
 *
 * Zoznam je cachovaný na deň (mení sa rádovo raz za roky), takže bez tohto by
 * zmazaná alebo vypnutá obec ostala v sprievodcovi vyberateľná ešte 24 hodín.
 */
class MunicipalityObserver
{
    public function saved(Municipality $municipality): void
    {
        $this->forget();
    }

    public function deleted(Municipality $municipality): void
    {
        $this->forget();
    }

    public function restored(Municipality $municipality): void
    {
        $this->forget();
    }

    private function forget(): void
    {
        Cache::forget(MunicipalityController::CACHE_KEY);
    }
}
