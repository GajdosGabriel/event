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
 * Prihláška na akciu (EventSignup), otázka aj správa návštevníka (CanalRelay)
 * sa správajú rovnako (docs/canal-ownership.md):
 *  - kontakt je e-mail kanála, záložne z podujatia; vyradené adresy sa preskočia,
 *  - zberný kanál sa neoslovuje, lebo sa nedá prevziať,
 *  - e-mail ponúka prevzatie kanála a má odkaz na odhlásenie,
 *  - ide cez Account (SendsViaAccount), vrátený e-mail adresu vyradí
 *    (CanalEmails::recordBounce),
 *  - celé to brzdí jediný vypínač `canals.simulate_takeover_mail`.
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

        $usable = $candidates->first(fn (string $candidate) => ! EmailSuppression::has($candidate));

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

    /** Adresa nespravovaného kanála, alebo null, keď sa cieľu písať nedá. */
    public function addressFor(Model $target): ?string
    {
        $canal = $this->canalOf($target);

        if ($canal === null || $canal->isManaged()) {
            return null;
        }

        $contact = $this->contact($canal, $target instanceof Event ? $target : null);

        return $contact['reason'] === null ? $contact['email'] : null;
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
        MailSimulator::send([NotificationFacade::route('mail', $email)], $notice, $this->simulated(), $subject);
    }

    public function simulated(): bool
    {
        return (bool) config('canals.simulate_takeover_mail', false);
    }
}
