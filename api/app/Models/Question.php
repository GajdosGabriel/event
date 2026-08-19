<?php

namespace App\Models;

use App\Enums\QuestionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Otázka z publika. Píše ju anonym bez účtu — viď migráciu `create_questions_table`.
 */
class Question extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /**
     * `author_hash` je pseudonym pisateľa a nemá čo opustiť server — ani
     * organizátorovi v dashboarde nie je na nič a v odpovedi by sa dal použiť
     * na spárovanie otázok od tej istej osoby naprieč nástenkami.
     */
    protected $hidden = ['author_hash'];

    protected $casts = [
        'status' => QuestionStatus::class,
        'upvotes_count' => 'integer',
        'answered_at' => 'datetime',
        'highlighted_at' => 'datetime',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(QuestionBoard::class, 'question_board_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(QuestionVote::class);
    }

    /** Otázky, ktoré smie vidieť verejnosť. */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', QuestionStatus::Published->value);
    }

    /**
     * Poradie na verejnej stránke aj na stene: najprv to, na čo sa práve
     * odpovedá, potom najžiadanejšie, a pri rovnosti hlasov najnovšie.
     *
     * Zodpovedané zámerne nepadajú na koniec — prednášajúci sa k nim vracia
     * a divák chce vidieť, že jeho otázka prešla.
     */
    public function scopeInWallOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('highlighted_at IS NULL')
            ->orderByDesc('upvotes_count')
            ->orderByDesc('id');
    }

    /**
     * Poradie na verejnom detaile podujatia, kde je z nástenky FAQ.
     *
     * Zodpovedané idú hore — návštevník sem prišiel pre odpoveď, nie pre
     * zoznam otvorených otázok, a zodpovedané sú aj to jediné, čo má zmysel
     * indexovať. Medzi nimi rozhoduje záujem (hlasy) a potom vek.
     *
     * Toto je zámerne iné poradie než `inWallOrder()`: na plátne je hore to,
     * na čo sa práve odpovedá, tu to, na čo sa už odpovedalo.
     */
    public function scopeInFaqOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('answered_at IS NULL')
            ->orderByDesc('upvotes_count')
            ->orderByDesc('id');
    }

    /** Otázka, ktorá už má odpoveď organizátora — jediná časť Q&A do JSON-LD. */
    public function scopeAnswered(Builder $query): Builder
    {
        return $query->whereNotNull('answered_at')->whereNotNull('answer_body');
    }

    public function isPublished(): bool
    {
        return $this->status === QuestionStatus::Published;
    }
}
