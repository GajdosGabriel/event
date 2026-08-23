<?php

namespace App\Models;

use App\Enums\QuestionStatus;
use App\Enums\QuestionVisibility;
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
     *
     * `author_email` je adresa, na ktorú si človek vypýtal odpoveď. Neukazuje sa
     * nikomu vrátane organizátora — ten na otázku odpovedá na stránke, nie do
     * schránky, a cudzie adresy sa v tomto projekte nezobrazujú nikde.
     */
    protected $hidden = ['author_hash', 'author_email'];

    protected $casts = [
        'status' => QuestionStatus::class,
        'visibility' => QuestionVisibility::class,
        'upvotes_count' => 'integer',
        'answered_at' => 'datetime',
        'answer_notified_at' => 'datetime',
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

    /** Prihlásený pisateľ, ak ním otázka vznikla. Anonymné otázky majú null. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Má sa po dopísaní odpovede ozvať e-mail?
     *
     * Adresa je vyplnená len na výslovné želanie, takže samotná jej prítomnosť
     * je súhlas. `answer_notified_at` je poistka proti druhej vlne: keď
     * organizátor odpoveď neskôr preformuluje, druhý e-mail už nechodí.
     */
    public function wantsAnswerNotification(): bool
    {
        return $this->author_email !== null && $this->answer_notified_at === null;
    }

    /**
     * Otázky, ktoré smie vidieť verejnosť.
     *
     * Sú tu obe podmienky naraz zámerne: `status` rieši moderovanie,
     * `visibility` sľub daný pisateľovi. Keby súkromnú otázku strážil len
     * `status`, stačilo by jedno kliknutie „zverejniť" v dashboarde (alebo
     * jedno miesto v kóde, ktoré na filter zabudne) a vec, ktorú niekto
     * napísal ako súkromnú, by visela na verejnej stránke. Preto je filter
     * jeden a používajú ho všetky verejné cesty — detail, stena, stream aj
     * prerender.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', QuestionStatus::Published->value)
            ->where('visibility', QuestionVisibility::Public->value);
    }

    /** Súkromné otázky a podnety — len pre organizátora v dashboarde. */
    public function scopeOnlyPrivate(Builder $query): Builder
    {
        return $query->where('visibility', QuestionVisibility::Private->value);
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

    public function isPrivate(): bool
    {
        return $this->visibility === QuestionVisibility::Private;
    }

    /**
     * Je otázka práve teraz vo verejnom zozname?
     *
     * Rovnaká dvojica podmienok ako `scopePubliclyVisible()`, len nad načítaným
     * riadkom. Podľa nej sa hýbe denormalizované `questions_count` — to je
     * verejné číslo a súkromná otázka doň nepatrí, aj keby mala stav
     * „zverejnená" (ten pri vypnutom moderovaní dostane každý nový riadok).
     */
    public function isPubliclyVisible(): bool
    {
        return $this->isPublished() && ! $this->isPrivate();
    }
}
