<?php

namespace App\Observers;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\Subscription;
use App\Models\Venue;
use App\Notifications\EventChanged;
use App\Services\Events\SubscriberDirectory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class EventObserver
{
    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        //
    }

    /**
     * Ozveme sa tým, čo si vypýtali „daj mi vedieť".
     *
     * Toto je celý dôvod, prečo nám niekto nechal adresu — na tlačidle
     * nesľubujeme newsletter, ale že sa ozveme, keď sa niečo zmení alebo zruší.
     * Pripomienka je až bonus.
     *
     * Sleduje sa len to, čo mení plán návštevníka: termín, miesto a to, či sa
     * podujatie vôbec koná. Oprava preklepu v popise e-mail vyvolať nesmie —
     * organizátori podujatia upravujú často a pár zbytočných správ stačí na to,
     * aby sa odhlásili všetci.
     */
    public function updated(Event $event): void
    {
        $cancelled = $this->becameCancelled($event);
        $changes = $cancelled ? [] : $this->describeChanges($event);

        if (! $cancelled && $changes === []) {
            return;
        }

        // Zmena konceptu nemá komu chodiť — verejný detail ho ani neukazuje,
        // takže odber naň nemohol vzniknúť. Kontrola je tu pre prípad, že sa
        // podujatie skrylo a znovu zverejnilo.
        if (! $cancelled && ! $this->isPubliclyVisible($event)) {
            return;
        }

        // Podujatie po termíne už nikoho nezaujíma; zmena jeho údajov je
        // typicky upratovanie v archíve.
        if ($event->start_at !== null && $event->start_at->isPast()) {
            return;
        }

        $subscribers = app(SubscriberDirectory::class)->forEvent($event);

        foreach ($subscribers as $subscription) {
            Notification::route('mail', $subscription->email)
                ->notify(new EventChanged($event, $subscription, $changes, $cancelled));
        }

        // Zrušené podujatie sa už nemá čím pripomenúť — odbery na ňom zaniknú
        // spolu s ním, nech nám v tabuľke neležia adresy bez účelu.
        if ($cancelled) {
            $subscribers->each(fn (Subscription $subscription) => $subscription->unsubscribe());
        }
    }

    /**
     * Handle the Event "deleted" event.
     */
    public function deleted(Event $event): void
    {
        //
    }

    /**
     * Handle the Event "restored" event.
     */
    public function restored(Event $event): void
    {
        //
    }

    /**
     * Handle the Event "force deleted" event.
     */
    public function forceDeleted(Event $event): void
    {
        //
    }

    /**
     * Zrušenie nemá vlastný stav — organizátor podujatie stiahne späť do
     * konceptu alebo ho archivuje. Z pohľadu človeka, ktorý sa naň chystal, je
     * to to isté: nekoná sa.
     *
     * Archiváciu po skončení robí `app:events-archive-finished` a tá sem
     * nedopadne, lebo o podujatie po termíne sa vyššie už nestaráme.
     */
    private function becameCancelled(Event $event): bool
    {
        if (! $event->wasChanged('status')) {
            return false;
        }

        // `getRawOriginal`, nie `getOriginal`: ten druhý na stĺpec aplikuje cast
        // a vrátil by ModelStatus, takže porovnanie s reťazcom by ticho zlyhalo
        // a zrušenie by sa nikdy neohlásilo.
        $before = $event->getRawOriginal('status');

        return $before === ModelStatus::Published->value
            && $event->status?->value !== ModelStatus::Published->value;
    }

    /**
     * Vety do e-mailu. Skladajú sa tu, lebo staré hodnoty pozná len observer —
     * notifikácia dostane už uložený model a nemala by ich z čoho zistiť.
     *
     * @return array<int, string>
     */
    private function describeChanges(Event $event): array
    {
        $changes = [];

        if ($event->wasChanged('start_at')) {
            $changes[] = __('mail.event_changed.change_start', [
                'from' => $this->formatDate($event->getOriginal('start_at')),
                'to' => $event->start_at?->format('d. m. Y H:i') ?? '—',
            ]);
        }

        if ($event->wasChanged('venue_id')) {
            $changes[] = __('mail.event_changed.change_venue', [
                'from' => $this->venueName($event->getOriginal('venue_id')),
                'to' => $event->loadMissing('venue')->venue?->name ?? '—',
            ]);
        }

        return $changes;
    }

    private function formatDate(mixed $raw): string
    {
        if (blank($raw)) {
            return '—';
        }

        try {
            return Carbon::parse($raw)->format('d. m. Y H:i');
        } catch (\Throwable) {
            return '—';
        }
    }

    private function venueName(mixed $venueId): string
    {
        if (blank($venueId)) {
            return '—';
        }

        return Venue::query()->whereKey($venueId)->value('name') ?? '—';
    }

    private function isPubliclyVisible(Event $event): bool
    {
        return in_array((string) $event->status?->value, ModelStatus::publiclyReadableValues(), true);
    }
}
