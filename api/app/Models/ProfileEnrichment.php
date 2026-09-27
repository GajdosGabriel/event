<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProfileEnrichment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'changes' => 'array', 'evidence' => 'array',
        'retry_at' => 'datetime', 'completed_at' => 'datetime',
        'notified_at' => 'datetime', 'notification_completed_at' => 'datetime',
        'notification_retry_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
