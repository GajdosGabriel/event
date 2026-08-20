<?php

namespace App\Models;

use App\Contracts\HasQuestionBoard;
use App\Enums\QuestionChannel;
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
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
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
     * Prijíma nástenka práve teraz otázky z daného vchodu?
     *
     * `is_open` je ručný vypínač organizátora, okno je automatika. Konce sa
     * kombinujú — organizátor vie nástenku zavrieť aj uprostred okna (napr. keď
     * sa niekto rozbehne spamovať) a naopak, otvorené `is_open` samo o sebe
     * nestačí, aby otázky chodili tri mesiace po akcii.
     *
     * Začiatok okna je ale vec plátna, nie stránky: `opens_at` drží QR adresu
     * mŕtvu, kým sa v sále skúša technika, no na verejnom detaile by tým istým
     * pravidlom zabil predakčné otázky organizátorovi — a to je práve to,
     * načo tam sekcia je. Preto rozhoduje kanál (QuestionChannel).
     *
     * Default je `Wall`, teda prísnejší variant: kto o rozdiel nevie,
     * nechtiac neotvorí nástenku skôr, než mal.
     */
    public function acceptsQuestions(QuestionChannel $channel = QuestionChannel::Wall): bool
    {
        if (! $this->is_open) {
            return false;
        }

        if ($channel->respectsOpensAt() && $this->opens_at !== null && $this->opens_at->isFuture()) {
            return false;
        }

        return $this->closes_at === null || $this->closes_at->isFuture();
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
