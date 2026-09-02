<?php

namespace App\Models\Traits;

use App\Models\ContentReview;
use App\Services\Content\ContentReviewService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Model má popis, ktorý sa po zverejnení dá skontrolovať.
 *
 * Hák visí na `saved()`, nie na controlleri publikovania. Dôvod je praktický:
 * zverejniť sa dá tromi cestami — poľom `status` vo formulári, tlačidlom
 * (RecordPublisher / EloquentEventRepository::publish) a príkazom
 * `app:events-publish-scheduled` pri naplánovanom čase. Kontrola visiaca na
 * controlleri by dve z nich prehliadla.
 *
 * Samotná kontrola sa tu nespúšťa — len sa naplánuje. Volanie OpenAI trvá
 * sekundy a uloženie formulára naň nemá čakať (rovnaký dôvod, prečo sondy
 * odkazov bežia príkazom a nie pri ukladaní).
 */
trait HasContentReview
{
    public static function bootHasContentReview(): void
    {
        static::saved(function (Model $model): void {
            app(ContentReviewService::class)->schedule($model);
        });

        static::deleted(function (Model $model): void {
            // Soft delete posudok neruší — archivovaný záznam sa môže vrátiť
            // a text sa medzitým nezmenil. Ruší ho až skutočné zmazanie, inak
            // by riadok ostal visieť na neexistujúcom modeli.
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->contentReview()->delete();
        });
    }

    /** @return MorphOne<ContentReview> */
    public function contentReview(): MorphOne
    {
        return $this->morphOne(ContentReview::class, 'reviewable');
    }

    /**
     * Komu sa ozveme s výhradami ku obsahu.
     *
     * Tá istá odpoveď ako pri pokazenom odkaze — je to tá istá otázka („kto sa
     * o tento záznam stará?") a padla už raz, vrátane pravidiel, kedy nie je
     * nikto (import z cudzieho zdroja).
     */
    public function contentReviewRecipient(): ?\App\Models\User
    {
        return method_exists($this, 'attributeIssueRecipient')
            ? $this->attributeIssueRecipient()
            : null;
    }
}
