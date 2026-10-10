<?php

namespace App\Services\Canals;

use App\Models\Canal;
use App\Notifications\CanalContactNotice;
use App\Services\SystemLog\MailSimulator;
use App\Services\SystemLog\Recorder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Kontaktný e-mail kanála je verejný údaj a zároveň adresa, cez ktorú sa
 * kanál preberá (CanalClaims). Preto:
 *  - po zmene je neoverený, kým nová adresa nepotvrdí odkaz z e-mailu
 *    (ak ju kanál už overenú nemal medzi ďalšími adresami),
 *  - pôvodná adresa dostane upozornenie — únos kanála sa tak neutají.
 *
 * Stav jednotlivých adries drží CanalEmails; tu sú len e-maily okolo zmeny.
 */
class CanalContactVerifier
{
    public function __construct(private CanalEmails $emails) {}

    /** Platnosť overovacieho odkazu. */
    public const LINK_DAYS = 7;

    /** Volá sa po uložení kanála; nič nerobí, ak primárna adresa ostala. */
    public function changed(Canal $canal, ?string $old): void
    {
        $new = $this->normalize($canal->email);
        $old = $this->normalize($old);

        if ($new === $old) {
            return;
        }

        $actor = Auth::user();
        Recorder::info('canals', 'contact_changed', "Kanál {$canal->name}: zmena kontaktného e-mailu",
            status: 'ok',
            recipient: $new,
            userId: $actor?->getAuthIdentifier(),
            subject: $canal,
            context: ['from' => $old, 'to' => $new, 'actor_id' => $actor?->getAuthIdentifier()],
        );

        // Nespravovaný kanál (import) nemá komu nič potvrdzovať — mení ho admin.
        if (! $canal->isManaged()) {
            return;
        }

        $simulate = (bool) config('canals.simulate_contact_mail', true);

        // Adresa, ktorú kanál už mal overenú, sa znova nepotvrdzuje.
        if ($new !== null && $canal->email_verified_at === null) {
            MailSimulator::send(
                [Notification::route('mail', $new)],
                new CanalContactNotice($canal, CanalContactNotice::VERIFY, $new, $this->verifyUrl($canal, $new)),
                $simulate,
                $canal,
            );
        }

        if ($old !== null) {
            MailSimulator::send(
                [Notification::route('mail', $old)],
                new CanalContactNotice($canal, CanalContactNotice::CHANGED, $new),
                $simulate,
                $canal,
            );
        }
    }

    /** Potvrdenie z odkazu. Platí len pre adresu, ktorá je na kanáli stále. */
    public function verify(Canal $canal, string $email): bool
    {
        if (! $this->emails->confirm($canal, $email, 'contact_link')) {
            return false;
        }

        Recorder::info('canals', 'contact_verified', "Kanál {$canal->name}: kontaktný e-mail overený",
            status: 'ok',
            recipient: $this->normalize($email),
            subject: $canal,
        );

        return true;
    }

    public function verifyUrl(Canal $canal, string $email): string
    {
        return URL::temporarySignedRoute(
            'public.canals.contact.verify',
            now()->addDays(self::LINK_DAYS),
            ['canal' => $canal->id, 'email' => $email],
        );
    }

    private function normalize(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
