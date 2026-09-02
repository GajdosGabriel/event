<?php

namespace App\Services\Content;

use App\Enums\ModelStatus;
use App\Models\ContentReview;
use App\Notifications\ContentReviewNotice;
use App\Services\OpenAI\ChatGPT;
use App\Services\Publishing\PublishReadiness;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Kontrola obsahu po zverejnení — plánovanie, beh a upozornenie.
 *
 * Postavené rovnako ako App\Services\Attributes\AttributeCheckService, lebo je
 * to tá istá úloha s iným predmetom: niečo o zázname zistíme strojovo,
 * zapíšeme to a majiteľovi sa ozveme len vtedy, keď to má cenu.
 *
 * Rozdelenie práce je zámerné:
 *
 *   schedule()  — beží pri každom uložení, je lacný (jeden zápis) a nič
 *                 nevolá von. Iba povie „tento text bude treba pozrieť".
 *   runDue()    — beží z príkazu v malých dávkach, volá OpenAI a rozosiela.
 *
 * Kontrola sa NIKDY nedotkne obsahu. Nájdené výhrady sú rada, nie zásah —
 * prepis púšťa človek vo formulári (panel „Vyplniť pomocou AI"), keď sa tak
 * rozhodne. Tichá úprava cudzieho zverejneného textu by bola niečo úplne iné.
 */
class ContentReviewService
{
    public function __construct(
        private readonly ChatGPT $chatGPT,
        private readonly PublishReadiness $readiness,
    ) {
    }

    /**
     * Naplánuje kontrolu, ak na ňu záznam dozrel.
     *
     * Volá sa z `saved()`, teda pri každom uložení — musí byť lacná a tichá.
     */
    public function schedule(Model $model): void
    {
        if (! config('content_review.enabled', true)) {
            return;
        }

        $kind = $this->readiness->aliasFor($model);

        if ($kind === null) {
            return;
        }

        // Lacná brzda pred akýmkoľvek dotazom. Kontrola sa týka dvoch vecí —
        // či je záznam vonku a čo je v texte — a `saved()` beží pri každom
        // uložení. Bez tejto podmienky by nočný import, ktorý prepisuje desiatky
        // polí na tisícoch podujatí, zaplatil jeden dotaz navyše za každé z nich.
        if (! $model->wasRecentlyCreated && ! $model->wasChanged(['status', 'body'])) {
            return;
        }

        // Kontroluje sa len to, čo je vonku. Koncept číta iba jeho autor a
        // e-mail o preklepe v texte, ktorý ešte píše, je otravovanie.
        if (! $this->isPublished($model)) {
            // Riadok sa nemaže — nesie `notified_at`, teda pamäť na to, že sme
            // sa už raz ozvali. Len prestane byť splatný. Nový koncept nemá čo
            // rušiť, jeho riadok ešte neexistuje.
            if (! $model->wasRecentlyCreated) {
                $model->contentReview()->whereNotNull('due_at')->update(['due_at' => null]);
            }

            return;
        }

        $review = $model->contentReview()->first();
        $body = (string) $model->getAttribute('body');
        $hash = $this->hash($body);

        // Text kratší než hranica nemá čo rozoberať — to už povedala
        // pripravenosť vo formulári, zadarmo a okamžite.
        if ($this->readiness->textLength($body) < (int) config('content_review.min_body_chars', 120)) {
            $review?->forceFill(['due_at' => null])->save();

            return;
        }

        // Ten istý text sme už posúdili. Znovu ho posielať by stálo peniaze a
        // vrátilo by to isté — uloženie kvôli inému poľu kontrolu nevyvoláva.
        if ($review !== null && $review->content_hash === $hash && $review->reviewed_at !== null) {
            return;
        }

        $dueAt = now()->addMinutes((int) config('content_review.delay_minutes', 15));

        if ($review === null) {
            $model->contentReview()->create(['content_hash' => $hash, 'due_at' => $dueAt]);

            return;
        }

        // Zmenený text ruší starý posudok: výhrady k predošlej verzii by po
        // prepise mátali (v e-maile aj vo formulári) a skóre by klamalo.
        $review->forceFill($review->content_hash === $hash ? [
            'due_at' => $dueAt,
        ] : [
            'content_hash' => $hash,
            'due_at' => $dueAt,
            'score' => null,
            'summary' => null,
            'issues' => null,
            'reviewed_at' => null,
            'last_error' => null,
        ])->save();
    }

    /**
     * Splatné kontroly, od najdlhšie čakajúcej.
     *
     * @return Collection<int, ContentReview>
     */
    public function due(?int $limit = null): Collection
    {
        $limit ??= (int) config('content_review.batch', 5);

        return ContentReview::query()->due()->with('reviewable')->limit($limit)->get();
    }

    /** Jedna kontrola. Vracia false, keď sa neposúdilo (zmiznutý model, chyba). */
    public function run(ContentReview $review): bool
    {
        $model = $review->reviewable;

        // Model medzitým zmizol alebo sa stiahol z výpisu — riadok prestane
        // byť splatný a ostane ležať, kým ho neoživí ďalšie zverejnenie.
        if ($model === null || ! $this->isPublished($model)) {
            $review->forceFill(['due_at' => null])->save();

            return false;
        }

        $kind = $this->readiness->aliasFor($model);
        $body = (string) $model->getAttribute('body');

        try {
            $result = $this->chatGPT->extractContentReview(
                (string) $kind,
                (string) $model->getAttribute('name'),
                $body,
                $this->contextFor($model),
            );
        } catch (\Throwable $e) {
            Log::warning('Kontrola obsahu zlyhala.', [
                'review_id' => $review->getKey(),
                'reviewable' => $model::class.'#'.$model->getKey(),
                'error' => $e->getMessage(),
            ]);

            // Nezopakuje sa hneď: výpadok OpenAI by inak celú dávku držal
            // a v každom behu by sa minula na tie isté zlyhávajúce riadky.
            $review->forceFill([
                'due_at' => now()->addHours(6),
                'last_error' => mb_substr($e->getMessage(), 0, 255),
            ])->save();

            return false;
        }

        $review->forceFill([
            'content_hash' => $this->hash($body),
            'score' => $result['score'],
            'summary' => $result['summary'],
            'issues' => $result['issues'],
            'reviewed_at' => now(),
            'due_at' => null,
            'last_error' => null,
        ])->save();

        $this->notify($review, $model);

        return true;
    }

    /**
     * Ozve sa majiteľovi, ak je čomu a komu.
     *
     * E-mail nie je zoznam chýb na opravu — je to pozvánka späť do formulára,
     * kde AI panel čaká s už zaškrtnutým tým, čo treba (viď
     * ContentReview::suggestedModes()).
     */
    private function notify(ContentReview $review, Model $model): void
    {
        $severity = (string) config('content_review.notify_from_severity', 'warning');
        $issues = $review->issuesAtLeast($severity);

        if ($issues === []) {
            return;
        }

        if (! $this->mayNotify($review)) {
            return;
        }

        $recipient = method_exists($model, 'contentReviewRecipient')
            ? $model->contentReviewRecipient()
            : null;

        // Bez majiteľa sa nemá komu ozvať — typicky importovaný záznam
        // z cudzieho zdroja. Posudok ostáva zapísaný, vidí ho admin.
        if ($recipient === null) {
            return;
        }

        $recipient->notify(new ContentReviewNotice($review, $model, $issues));

        $review->forceFill(['notified_at' => now()])->save();
    }

    private function mayNotify(ContentReview $review): bool
    {
        if ($review->notified_at === null) {
            return true;
        }

        $cooldown = max(0, (int) config('content_review.notice_cooldown_days', 14));

        return $cooldown > 0 && $review->notified_at->addDays($cooldown)->isPast();
    }

    /**
     * Čo ešte o zázname vieme — aby model nevyčítal ako chybu to, čo v texte
     * chýbať smie, lebo to stojí v inom poli formulára.
     *
     * @return array<string, string>
     */
    private function contextFor(Model $model): array
    {
        return array_filter([
            'Obec' => (string) ($model->relationLoaded('municipality')
                ? $model->municipality?->name
                : $model->getAttribute('city')),
            'Začiatok' => $model->getAttribute('start_at')?->format('d. m. Y H:i') ?? '',
        ], 'filled');
    }

    private function isPublished(Model $model): bool
    {
        $status = $model->getAttribute('status');
        $value = $status instanceof ModelStatus ? $status->value : (string) $status;

        return $value === ModelStatus::Published->value;
    }

    /**
     * Odtlačok posudzovaného textu. Normalizujú sa biele znaky — prehodený
     * zalomenie riadku nie je zmena obsahu a nemá platiť za novú kontrolu.
     */
    private function hash(string $body): string
    {
        return hash('sha256', trim(preg_replace('/\s+/u', ' ', $body) ?? ''));
    }
}
