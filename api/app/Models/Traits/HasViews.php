<?php

namespace App\Models\Traits;

use App\Models\View;

/**
 * Počítadlo zobrazení verejného detailu.
 *
 * `views_count` je zámerne skrytý: verejné endpointy serializujú modely priamo
 * cez toArray() (Public\EventController::show a spol.), takže bez $hidden by
 * číslo uvidel každý návštevník. Do odpovede ho pridávajú resources, a to len
 * keď má používateľ na záznam právo `view` — teda organizátor alebo admin.
 */
trait HasViews
{
    public function initializeHasViews(): void
    {
        $this->hidden[] = 'views_count';

        $this->mergeCasts(['views_count' => 'integer']);
    }

    public function views()
    {
        return $this->morphMany(View::class, 'viewable');
    }
}
