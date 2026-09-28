<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedno oslovenie organizátora po akcii (App\Services\Canals\CanalOutreachSender).
 */
class CanalOutreach extends Model
{
    public const UPDATED_AT = null;

    public const SENT = 'sent';
    public const SIMULATED = 'simulated';

    protected $table = 'canal_outreach';

    protected $guarded = [];

    protected $casts = [
        'stats' => 'array',
        'created_at' => 'datetime',
    ];

    public function canal(): BelongsTo
    {
        return $this->belongsTo(Canal::class)->withTrashed();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
