<?php

namespace App\Services\Canals;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Notifications\CanalEventPublishedOffer;
use Illuminate\Support\Facades\Cache;

/**
 * Technický e-mail kontaktu nespravovaného kanála: zverejnili sme jeho akciu
 * (pri prvej akcii nového kanála aj „vytvorili sme váš kanál") a kanál si
 * môže prevziať.
 *
 * Nejde cez Account Emailing (kampane), ale ako ostatné e-maily na kontakt
 * kanála cez CanalTakeover: rovnaký výber adresy (kanál, záložne akcia),
 * nedoručiteľné adresy sa preskočia. Adresa nemusí byť overená. Nepíše sa
 * kanálom s `event_offer_disabled` (prepínač v úprave kanála v admine) ani na
 * domény zo zoznamu `canals.event_offer_excluded_domains`. Píše sa pri každej
 * akcii, ku každej najviac raz (`events.takeover_notified_at`), a kanálu
 * najviac raz za deň — import, ktorý naraz zverejní viac akcií, tak pošle
 * jeden e-mail (k prvej z nich), ostatné akcie toho dňa e-mail nedostanú.
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

        if ($reason !== null || self::excludedDomain($contact)) {
            return;
        }

        // Kontakt, ktorý už v tíme kanála je, ponuku prevzatia nepotrebuje.
        if ($canal->users()->where('users.email', $contact)->exists()) {
            return;
        }

        // Kontrola denného stropu a rezervácia akcie pod zámkom kanála — súbežné
        // joby z importu nepošlú kanálu dva e-maily v ten istý deň.
        $claimed = Cache::lock('canal-event-offer:'.$canal->id, 10)->block(5, function () use ($canal, $event) {
            if ($this->notifiedToday($canal)) {
                return 0;
            }

            return Event::query()
                ->whereKey($event->id)
                ->whereNull('takeover_notified_at')
                ->update(['takeover_notified_at' => now()]);
        });

        if ($claimed === 0) {
            return;
        }

        $newCanal = $canal->created_at !== null
            && $canal->created_at->gte(now()->subDays(self::NEW_CANAL_DAYS))
            && ! $canal->events()->whereKeyNot($event->id)->whereNotNull('takeover_notified_at')->exists();

        $invitation = $this->takeover->invitation($canal, $contact);

        $this->takeover->send($contact, new CanalEventPublishedOffer($event, $invitation, $newCanal), $event);
    }

    /**
     * Adresa na doméne (alebo jej subdoméne) zo zoznamu
     * `canals.event_offer_excluded_domains` — zdroje importu s množstvom akcií,
     * ktorým by šiel e-mail pri každej z nich.
     */
    public static function excludedDomain(?string $email): bool
    {
        $domain = mb_strtolower((string) substr(strrchr((string) $email, '@') ?: '', 1));

        if ($domain === '') {
            return false;
        }

        foreach ((array) config('canals.event_offer_excluded_domains', []) as $excluded) {
            $excluded = mb_strtolower(trim((string) $excluded));

            if ($excluded !== '' && ($domain === $excluded || str_ends_with($domain, '.'.$excluded))) {
                return true;
            }
        }

        return false;
    }

    /** Kanál už dnes e-mail o zverejnenej akcii dostal. */
    private function notifiedToday(Canal $canal): bool
    {
        return $canal->events()->where('takeover_notified_at', '>=', today())->exists();
    }

    private function eligible(Event $event): bool
    {
        $canal = $event->canal;
        $end = $event->end_at ?? $event->start_at;

        return $canal !== null
            && ! $canal->isManaged()
            && ! $canal->event_offer_disabled
            && $event->status === ModelStatus::Published
            && $event->takeover_notified_at === null
            && ($end === null || $end->isFuture());
    }
}
