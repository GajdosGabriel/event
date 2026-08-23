<?php

namespace App\Models;

use App\Contracts\HasQuestionBoard;
use App\Enums\QuestionStatus;
use App\Support\BoardToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Nástenka otázok z publika — jedna na podujatie alebo na jeden workshop.
 *
 * Nesie nastavenia aj token, ktorý je autorizáciou verejnej adresy `/q/{token}`
 * (rovnaká konvencia ako RSVP odkaz v e-maile: token v odkaze JE autorizácia).
 */
class QuestionBoard extends Model
{
    /**
     * Whitelist cieľov: alias => model. Jediné miesto, kde sa povolené typy
     * definujú — presne ako Message::TARGETS. `boardable_type` sa ukladá ako
     * plný názov triedy (default morph), aby sa neprepísala morph mapa
     * zdieľaná s files.fileable_type.
     */
    public const TARGETS = [
        'event' => Event::class,
        'workshop' => TicketType::class,
    ];

    protected $guarded = [];

    protected $hidden = ['token'];

    protected $casts = [
        'is_open' => 'boolean',
        'moderation' => 'boolean',
        'show_questions' => 'boolean',
        'allow_upvotes' => 'boolean',
        'ask_for_name' => 'boolean',
        'allow_private' => 'boolean',
        'questions_count' => 'integer',
    ];

    /**
     * Token, ktorý sa v tabuľke ešte nevyskytuje. Kolízia je pri 2^50
     * možnostiach prakticky nemožná, ale unikátny index by z nej spravil
     * 500-ku pri zakladaní nástenky, čo je zbytočná cena za neopakovanie cyklu.
     */
    public static function freshToken(): string
    {
        do {
            $token = BoardToken::generate();
        } while (static::query()->where('token', $token)->exists());

        return $token;
    }

    /** Cieľ nástenky — Event alebo TicketType (workshop). */
    public function boardable(): MorphTo
    {
        return $this->morphTo();
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /** Alias typu cieľa (event/workshop) pre uložený morph class. */
    public function targetType(): ?string
    {
        return array_search($this->boardable_type, self::TARGETS, true) ?: null;
    }

    /**
     * Prijíma nástenka práve teraz otázky?
     *
     * Jediná otázka, jediná odpoveď: `is_open`. Nástenka mala kedysi aj časové
     * okno, ktoré sa ju snažilo otvárať a zatvárať samo podľa termínu — z toho
     * bola len ďalšia vec, ktorá sa vie pokaziť, a organizátor si aj tak
     * odpovedal na to isté „berieme otázky, alebo nie?". Zavretá nástenka sa
     * dá stále čítať, len sa do nej nedá písať.
     */
    public function acceptsQuestions(): bool
    {
        return (bool) $this->is_open;
    }

    /**
     * Prijíma nástenka aj súkromné otázky a podnety?
     *
     * Viazané na `is_open` rovnako ako verejné otázky — zavretá nástenka
     * neberie nič. Súkromný vstup má navyše vlastný vypínač: je to záväzok
     * odpovedať e-mailom a kto ho nechce dať, nemá ho ani sľubovať.
     */
    public function acceptsPrivateQuestions(): bool
    {
        return $this->acceptsQuestions() && (bool) $this->allow_private;
    }

    /** Stav, v ktorom má vzniknúť nová otázka. */
    public function statusForNewQuestion(): QuestionStatus
    {
        return $this->moderation ? QuestionStatus::Pending : QuestionStatus::Published;
    }

    /**
     * Podujatie, ktorého viditeľnosť a kanál nástenka dedí. Vracia null, keď
     * cieľ neexistuje (zmazaný workshop) — volajúci to musí brať ako 404.
     */
    public function event(): ?Event
    {
        $target = $this->target();

        return $target instanceof HasQuestionBoard ? $target->questionBoardEvent() : null;
    }

    public function title(): string
    {
        $target = $this->target();

        return $target instanceof HasQuestionBoard ? $target->questionBoardTitle() : '';
    }

    /**
     * Cieľ nástenky bez pádu na preventLazyLoading. Explicitný dotaz namiesto
     * `$this->boardable` je v tomto projekte konvencia — accessory na Evente to
     * robia rovnako, lebo mimo produkcie je lenivé načítanie tvrdá chyba.
     */
    private function target(): ?Model
    {
        return $this->relationLoaded('boardable')
            ? $this->getRelation('boardable')
            : $this->boardable()->first();
    }
}
