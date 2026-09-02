<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Posudok zverejneného popisu jedného záznamu — „ako je na tom tento text".
 *
 * Zapisuje ho výhradne App\Services\Content\ContentReviewService; model sám
 * drží len prevody a dopyty. Jeden riadok na záznam, prepisuje sa na mieste —
 * história posudkov nikoho nezaujíma, aktuálny stav áno.
 */
class ContentReview extends Model
{
    protected $guarded = [];

    protected $casts = [
        'issues' => 'array',
        'score' => 'integer',
        'due_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Splatné kontroly, od najdlhšie čakajúcej. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->orderBy('due_at');
    }

    /**
     * Výhrady aspoň zadanej závažnosti.
     *
     * @return array<int, array{severity: string, mode: string, message: string, quote: string}>
     */
    public function issuesAtLeast(string $severity): array
    {
        $order = array_flip(\App\Services\OpenAI\PromptContentReview::SEVERITIES);
        $threshold = $order[$severity] ?? 0;

        return array_values(array_filter(
            (array) ($this->issues ?? []),
            static fn (array $issue) => ($order[$issue['severity'] ?? ''] ?? -1) >= $threshold,
        ));
    }

    /**
     * Režimy panela „Vyplniť pomocou AI", ktoré nájdené výhrady riešia.
     *
     * Presne toto ide do odkazu v e-maile (`?ai=grammar,expand`), aby sa
     * formulár otvoril s už zaškrtnutým tým, čo treba — človek po kliknutí
     * nemá hádať, ktorý prepínač je jeho.
     *
     * @return array<int, string>
     */
    public function suggestedModes(): array
    {
        $modes = array_column((array) ($this->issues ?? []), 'mode');

        // Poradie z promptu, nie poradie výskytu — v odkaze aj v e-maile má
        // byť stabilné, nech sa dve kontroly toho istého textu nelíšia.
        return array_values(array_intersect(
            \App\Services\OpenAI\PromptContentReview::MODES,
            array_unique(array_filter($modes)),
        ));
    }
}
