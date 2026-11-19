<?php

namespace App\Services\Canals;

use App\Models\Canal;
use App\Models\CanalInvitation;
use App\Models\EmailSuppression;
use App\Models\Event;
use App\Services\Imports\CollectionCanal;
use App\Services\SystemLog\MailSimulator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Jedno miesto pre e-maily na kontakt kanála, ktorý nikto nespravuje.
 *
 * Prihláška na akciu (EventSignup), zverejnená akcia (CanalEventOffer), otázka
 * aj správa návštevníka (CanalRelay) sa správajú rovnako (docs/canal-ownership.md):
 *  - kontakt je e-mail kanála, záložne z podujatia; nedoručiteľné adresy sa
 *    preskočia, odhlásenie z kampaní nie (technický e-mail),
 *  - otázku a správu návštevníka prijíma aj neoverený kontakt (addressFor),
 *    pisateľ je mu však anonymizovaný; bez kontaktu nie je ani rezervácia
 *    (hasOrganizerContact),
 *  - zberný kanál sa neoslovuje, lebo sa nedá prevziať,
 *  - e-mail ponúka prevzatie kanála; odkaz na odhlásenie nemá (technický e-mail),
 *  - ide cez Account (SendsViaAccount), vrátený e-mail adresu vyradí
 *    (CanalEmails::recordBounce),
 */
class CanalTakeover
{
    public const NO_CONTACT = 'none';

    public const COLLECTION = 'collection';

    public const SUPPRESSED = 'suppressed';

    public function __construct(private CanalInviter $inviter) {}

    /**
     * Na akú adresu písať, alebo prečo nikam. `reason` je null, keď sa písať
     * smie. Pri `suppressed` vracia aj adresu, aby sa dalo povedať, ktorá to je.
     *
     * @return array{reason: ?string, email: ?string}
     */
    public function contact(?Canal $canal, ?Event $event = null): array
    {
        if ($canal === null) {
            return ['reason' => self::NO_CONTACT, 'email' => null];
        }

        if (CollectionCanal::is($canal)) {
            return ['reason' => self::COLLECTION, 'email' => null];
        }

        $candidates = collect([$canal->email, $event?->email])
            ->map(fn ($candidate) => mb_strtolower(trim((string) $candidate)))
            ->filter(fn (string $candidate) => $candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL))
            ->values();

        if ($candidates->isEmpty()) {
            return ['reason' => self::NO_CONTACT, 'email' => null];
        }

        // Technický e-mail: odhlásenie z kampaní ho nezastaví, len nedoručiteľnosť.
        $usable = $candidates->first(fn (string $candidate) => ! EmailSuppression::bounced($candidate));

        return $usable !== null
            ? ['reason' => null, 'email' => $usable]
            : ['reason' => self::SUPPRESSED, 'email' => $candidates->first()];
    }

    /** Kanál, ktorému sa dá písať cez daný cieľ (kanál alebo podujatie). */
    public function canalOf(Model $target): ?Canal
    {
        return match (true) {
            $target instanceof Canal => $target,
            $target instanceof Event => $target->canal()->first(),
            default => null,
        };
    }

    /**
     * Adresa, na ktorú smie návštevník poslať otázku alebo správu, alebo null.
     *
     * Stačí aj neoverený kontakt (z importu, pri podujatí aj záložný e-mail
     * podujatia) — e-mail o otázke ponúka prevzatie a odpoveď organizátora
     * kontakt overí. Pisateľa vtedy organizátorovi anonymizujeme (CanalRelay)
     * a návštevníka front upozorní, že odpoveď nemusí prísť (isUnverified).
     */
    public function addressFor(Model $target): ?string
    {
        $canal = $this->canalOf($target);

        if ($canal === null || $canal->isManaged()) {
            return null;
        }

        $contact = $this->contact($canal, $target instanceof Event ? $target : null);

        return $contact['reason'] === null ? $contact['email'] : null;
    }

    /**
     * Má podujatie komu oznámiť rezerváciu? Spravovaný kanál áno; nespravovaný
     * len s kontaktom (e-mail kanála, záložne podujatia), zberný nikdy.
     *
     * Volá sa z Event::isReservable() pri každom riadku výpisu, preto bez
     * dotazu na nedoručiteľné adresy — tie rieši až samotné oznámenie (contact).
     */
    public function hasOrganizerContact(Event $event): bool
    {
        $canal = $event->canal;

        if (! $canal instanceof Canal || CollectionCanal::is($canal)) {
            return false;
        }

        if ($canal->isManaged()) {
            return true;
        }

        return collect([$canal->email, $event->email])
            ->contains(fn ($candidate) => filter_var(trim((string) $candidate), FILTER_VALIDATE_EMAIL) !== false);
    }

    /** Kontakt kanála bez správcu, ktorý sa nám ešte neozval — organizátorovi pisateľa anonymizujeme. */
    public function isUnverified(?Canal $canal): bool
    {
        return $canal !== null && ! $canal->isManaged() && $canal->email_verified_at === null;
    }

    /**
     * Kanál bez správcu a bez overeného kontaktu: e-mail o prihláške síce môže
     * odísť, ale nevieme, či si ho niekto prečíta. Návštevník to má vedieť ešte
     * pred rezerváciou.
     */
    public function organizerUnconfirmed(Event $event): bool
    {
        $canal = $this->canalOf($event);

        return $canal === null || $this->isUnverified($canal);
    }

    public function invitation(Canal $canal, string $email): CanalInvitation
    {
        return $this->inviter->ensureOwnerInvitation($canal, $email);
    }

    public function claimUrl(CanalInvitation $invitation): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/pozvanka/'.$invitation->token;
    }

    public function send(string $email, Notification $notice, ?Model $subject = null): void
    {
        MailSimulator::send([NotificationFacade::route('mail', $email)], $notice, false, $subject);
    }
}
