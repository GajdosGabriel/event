<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Človek, ktorý sa chcel na podujatie prihlásiť, ale vstupenka sa mu vystaviť
 * nedala — organizátor sa mu má ozvať (App\Services\Tickets\EventSignup).
 */
class EventInterest extends Model
{
    protected $guarded = [];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
