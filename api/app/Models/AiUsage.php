<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedno volanie OpenAI. Cena sa počíta pri zápise z cenníka v config/openai.php,
 * takže neskoršia zmena cenníka staré riadky neprepíše.
 */
class AiUsage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'feature', 'source', 'model', 'prompt_tokens', 'completion_tokens', 'cost_usd',
        'success', 'user_id', 'canal_id', 'subject_type', 'subject_id',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'cost_usd' => 'float',
        'success' => 'boolean',
    ];

    public function canal(): BelongsTo
    {
        return $this->belongsTo(Canal::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Cena v USD podľa cenníka (USD za milión tokenov). Neznámy model = 0. */
    public static function price(string $model, int $promptTokens, int $completionTokens): float
    {
        // Názvy modelov majú bodky (gpt-4.1-mini), preto nie config('a.b.c').
        // API vracia model aj s dátumom verzie (gpt-4o-mini-2024-07-18).
        $table = (array) config('openai.prices', []);
        $prices = $table[$model] ?? $table[preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $model)] ?? null;

        if (! is_array($prices)) {
            return 0.0;
        }

        return ($promptTokens * $prices['input'] + $completionTokens * $prices['output']) / 1_000_000;
    }
}
