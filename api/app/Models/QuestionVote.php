<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hlas za otázku. Bez `updated_at` — hlas sa nemení, iba pribudne alebo zmizne.
 */
class QuestionVote extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $hidden = ['voter_hash'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
