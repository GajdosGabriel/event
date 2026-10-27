<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedno oslovenie organizátora (App\Services\Canals\CanalOutreachSender).
 *
 * Vlastný e-mail Eventu: `sent` / `simulated`. Cez Account Emailing:
 * `reported` → `queued` (kampaň mail zaradila) alebo `not_queued` (žiadna
 * aktívna kampaň) → podľa webhooku `sent` / `failed` / `bounced`.
 */
class CanalOutreach extends Model
{
    public const UPDATED_AT = null;

    public const SENT = 'sent';

    public const SIMULATED = 'simulated';

    public const REPORTED = 'reported';

    public const QUEUED = 'queued';

    public const NOT_QUEUED = 'not_queued';

    public const FAILED = 'failed';

    public const BOUNCED = 'bounced';

    /** Stavy, ktoré sa rátajú do stropu oslovení (ostré). */
    public const COUNTED = [self::SENT, self::REPORTED, self::QUEUED];

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

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(CanalInvitation::class);
    }
}
