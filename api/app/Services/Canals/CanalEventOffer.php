<?php

namespace App\Services\Canals;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Notifications\CanalEventPublishedOffer;

/**
 * Technický e-mail kontaktu nespravovaného kanála: zverejnili sme jeho akciu
 * (pri prvej akcii nového kanála aj „vytvorili sme váš kanál") a kanál si
 * môže prevziať.
 *
 * Nejde cez Account Emailing (kampane), ale ako ostatné e-maily na kontakt
 * kanála cez CanalTakeover: rovnaký výber adresy (kanál, záložne akcia),
 * vyradené adresy sa preskočia, brzdou je `canals.simulate_takeover_mail`.
 * Adresa nemusí byť overená. Píše sa pri každej akcii, ku každej najviac raz
 * (`events.takeover_notified_at`).
 */
class CanalEventOffer
{
    /** Kanál je „nový", keď vznikol najviac pred toľkými dňami a ešte nič nedostal. */
    public const NEW_CANAL_DAYS = 2;

    public function __construct(private CanalTakeover $takeover) {}

    public function notify(int $eventId): void
    {
        $event = Event::query()->with(['canal', 'venue'])->find($eventId);

        if (! $event || ! $this->eligible($event)) {
            return;
        }

        $canal = $event->canal;
        ['reason' => $reason, 'email' => $contact] = $this->takeover->contact($canal, $event);

        if ($reason !== null) {
            return;
        }

        // Kontakt, ktorý už v tíme kanála je, ponuku prevzatia nepotrebuje.
        if ($canal->users()->where('users.email', $contact)->exists()) {
            return;
        }

        // Rezervácia akcie pred odoslaním — dva súbežné joby nepošlú dva e-maily.
        $claimed = Event::query()
            ->whereKey($event->id)
            ->whereNull('takeover_notified_at')
            ->update(['takeover_notified_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $newCanal = $canal->created_at !== null
            && $canal->created_at->gte(now()->subDays(self::NEW_CANAL_DAYS))
            && ! $canal->events()->whereKeyNot($event->id)->whereNotNull('takeover_notified_at')->exists();

        $invitation = $this->takeover->invitation($canal, $contact);

        $this->takeover->send($contact, new CanalEventPublishedOffer($event, $invitation, $newCanal), $event);
    }

    private function eligible(Event $event): bool
    {
        $canal = $event->canal;
        $end = $event->end_at ?? $event->start_at;

        return $canal !== null
            && ! $canal->isManaged()
            && $event->status === ModelStatus::Published
            && $event->takeover_notified_at === null
            && ($end === null || $end->isFuture());
    }
}
