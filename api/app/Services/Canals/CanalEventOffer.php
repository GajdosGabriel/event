<?php

namespace App\Services\Canals;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Notifications\CanalEventPublishedOffer;
use Illuminate\Database\Eloquent\Builder;
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
 *
 * Import zakladá kanál najprv bez kontaktu a e-mail dohľadá až neskôr, takže
 * pri zverejnení akcie často nie je komu písať. Preto sa ponuka pošle aj
 * vtedy, keď kanál alebo jeho akcia e-mail dostane či zmení (notifyCanal) —
 * k najbližšej akcii, ku ktorej e-mail ešte neodišiel, ak kanál nedostal
 * ponuku posledných CONTACT_CHANGE_QUIET_DAYS dní.
 */
class CanalEventOffer
{
    /** Kanál je „nový", keď vznikol najviac pred toľkými dňami a ešte nič nedostal. */
    public const NEW_CANAL_DAYS = 2;

    /** Po zmene kontaktu sa nepíše kanálu, ktorý ponuku dostal pred menej ako toľkými dňami. */
    public const CONTACT_CHANGE_QUIET_DAYS = 14;

    public function __construct(private CanalTakeover $takeover) {}

    /** Pošle ponuku k akcii, ak sa smie. Vráti, či e-mail odišiel. */
    public function notify(int $eventId): bool
    {
        $event = Event::query()->with(['canal', 'venue'])->find($eventId);
        $contact = $event ? $this->recipient($event) : null;

        if ($contact === null) {
            return false;
        }

        $canal = $event->canal;

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
            return false;
        }

        $newCanal = $canal->created_at !== null
            && $canal->created_at->gte(now()->subDays(self::NEW_CANAL_DAYS))
            && ! $canal->events()->whereKeyNot($event->id)->whereNotNull('takeover_notified_at')->exists();

        $invitation = $this->takeover->invitation($canal, $contact);

        $this->takeover->send($contact, new CanalEventPublishedOffer($event, $invitation, $newCanal), $event);

        return true;
    }

    /**
     * Kanál alebo jeho akcia dostali kontakt či nový kontakt: ponuka k
     * najbližšej zverejnenej akcii, ku ktorej e-mail ešte neodišiel. Kanálu,
     * ktorý ponuku dostal nedávno, sa nepíše — import, ktorý adresu prepisuje,
     * tak nepošle sériu e-mailov.
     */
    public function notifyCanal(int $canalId): bool
    {
        $canal = Canal::query()->find($canalId);

        if (! $canal || $canal->isManaged() || $canal->event_offer_disabled) {
            return false;
        }

        if ($canal->events()->where('takeover_notified_at', '>=', now()->subDays(self::CONTACT_CHANGE_QUIET_DAYS))->exists()) {
            return false;
        }

        foreach ($this->pendingEvents($canal)->pluck('id') as $eventId) {
            if ($this->notify((int) $eventId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Zverejnené akcie kanála, ktoré ešte neskončili a e-mail k nim neodišiel,
     * od najbližšej.
     *
     * @return Builder<Event>
     */
    public function pendingEvents(Canal $canal): Builder
    {
        return Event::query()
            ->where('canal_id', $canal->id)
            ->where('status', ModelStatus::Published->value)
            ->whereNull('takeover_notified_at')
            ->where(fn (Builder $q) => $q->whereRaw('COALESCE(end_at, start_at) IS NULL')
                ->orWhereRaw('COALESCE(end_at, start_at) > ?', [now()]))
            ->orderByRaw('start_at IS NULL')
            ->orderBy('start_at')
            ->orderBy('id');
    }

    /**
     * Komu by išla ponuka k tejto akcii, alebo null, keď nikomu. Denný strop
     * kanála nerieši — ten sa kontroluje až pod zámkom pri odoslaní.
     */
    public function recipient(Event $event): ?string
    {
        if (! $this->eligible($event)) {
            return null;
        }

        $canal = $event->canal;
        ['reason' => $reason, 'email' => $contact] = $this->takeover->contact($canal, $event);

        if ($reason !== null || self::excludedDomain($contact)) {
            return null;
        }

        // Kontakt, ktorý už v tíme kanála je, ponuku prevzatia nepotrebuje.
        if ($canal->users()->where('users.email', $contact)->exists()) {
            return null;
        }

        return $contact;
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
