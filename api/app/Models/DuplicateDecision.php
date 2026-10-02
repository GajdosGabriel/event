<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rozhodnutie importu o tom, či je nový kanál/miesto duplicitou existujúceho.
 *
 * @property string $entity canal|venue
 * @property string $decision same|distinct|uncertain
 */
class DuplicateDecision extends Model
{
    public const ENTITY_CANAL = 'canal';

    public const ENTITY_VENUE = 'venue';

    /** Ten istý subjekt — import použil existujúci záznam. */
    public const SAME = 'same';

    /** Rôzne subjekty — import založil nový záznam. */
    public const DISTINCT = 'distinct';

    /** Nejasné — nový záznam vznikol, ale čaká na kontrolu človekom. */
    public const UNCERTAIN = 'uncertain';

    public const SOURCE_RULE = 'rule';

    public const SOURCE_AI = 'ai';

    protected $guarded = [];

    protected $casts = [
        'confidence' => 'float',
        'reviewed_at' => 'datetime',
    ];
}
