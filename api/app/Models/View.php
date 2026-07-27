<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jedno zobrazenie verejného detailu. Zapisuje sa hromadne cez query builder
 * (ViewRecorder), model slúži na čítanie a testy.
 */
class View extends Model
{
    protected $guarded = [];

    // Tabuľka má len created_at, žiadne updated_at — riadok sa nikdy needituje.
    public $timestamps = false;

    protected $casts = [
        'viewed_on' => 'date',
        'created_at' => 'datetime',
    ];

    public function viewable()
    {
        return $this->morphTo();
    }
}
