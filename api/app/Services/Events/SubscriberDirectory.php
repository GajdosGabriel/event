<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Kto si vypýtal „daj mi vedieť" k podujatiu — jediný zdroj pravdy pre
 * pripomienku aj pre správu o zmene.
 *
 * Dvojička [AttendeeDirectory](AttendeeDirectory.php), ale iné publikum a iné
 * pravidlá: účastník má lístok a píše sa mu z pozície hostiteľa, odberateľ nám
 * dal iba adresu a každý e-mail mu musí ponúknuť odhlásenie. Preto sú to dve
 * triedy a nie jeden zoznam s príznakom.
 */
class SubscriberDirectory
{
    /**
     * Živé odbery podujatia. Odhlásený riadok má `email` NULL a `active()` ho
     * vynechá — netreba ho filtrovať zvlášť.
     *
     * @return Collection<int, Subscription>
     */
    public function forEvent(Event $event): Collection
    {
        return $this->query($event)->get();
    }

    /**
     * Odbery, ktorým ešte nešla pripomienka. `notified_at` je poistka proti
     * druhej vlne pri ďalšom behu príkazu — ako `events.reminder_sent_at` pri
     * účastníkoch, len na úrovni riadku: odber môže vzniknúť aj potom, čo
     * podujatie svoje okno prekročilo, a taký človek pripomienku dostať nemá.
     *
     * @return Collection<int, Subscription>
     */
    public function pendingReminderForEvent(Event $event): Collection
    {
        return $this->query($event)->whereNull('notified_at')->get();
    }

    /** Existuje na podujatí vôbec niekto, komu sa oplatí posielať? */
    public function hasAnyForEvent(Event $event): bool
    {
        return $this->query($event)->exists();
    }

    private function query(Event $event): Builder
    {
        return Subscription::query()
            ->active()
            ->where('subscribable_type', Event::class)
            ->where('subscribable_id', $event->id);
    }
}
