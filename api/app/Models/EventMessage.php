<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Záznam o správe organizátora účastníkom (App\Services\Events\AttendeeMessenger). */
class EventMessage extends Model
{
    public const KIND_ANNOUNCEMENT = 'announcement';

    public const KIND_CANCELLATION = 'cancellation';

    protected $guarded = [];

    protected $casts = [
        'recipients' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
