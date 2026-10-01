<?php

namespace App\Notifications;

use App\Enums\CanalRole;
use App\Models\Canal;
use App\Models\CanalClaim;
use App\Models\User;
use App\Support\DashboardUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Zmena vlastníctva kanála (App\Services\Canals\CanalAuditor).
 *
 * `$change`:
 *  - `claimed`       — kanál prevzal organizátor; ide jemu a na kontakt kanála,
 *  - `owner_added`   — pribudol vlastník; ide jemu a ostatným vlastníkom,
 *  - `owner_removed` — vlastník bol odobratý alebo preradený; ide jemu a ostatným.
 *
 * Text sa líši podľa toho, či adresát je dotknutý člen, iný vlastník, alebo
 * kontaktná adresa bez účtu (tej ukážeme len maskovaný e-mail).
 * Kým platí `canals.simulate_ownership_mail`, e-mail neodchádza (MailSimulator).
 */
class CanalOwnershipChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public const CLAIMED = 'claimed';

    public const OWNER_ADDED = 'owner_added';

    public const OWNER_REMOVED = 'owner_removed';

    public function __construct(
        public readonly Canal $canal,
        public readonly string $change,
        public readonly User $member,
        public readonly ?CanalRole $role = null,
        public readonly ?CanalClaim $claim = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $canal = $this->canal->name ?: __('mail.canal_invitation.canal_fallback');
        $key = 'mail.canal_ownership.'.$this->change;
        $audience = $this->audience($notifiable);

        $mail = (new MailMessage)
            ->subject(__($key.'.subject', ['canal' => $canal]))
            ->greeting(__('mail.common.greeting'))
            ->line(__($key.'.'.$audience, [
                'canal' => $canal,
                'member' => $this->member->maskedEmail() ?? $this->member->displayName(),
                'role' => $this->role?->label() ?? '',
            ]));

        if ($audience === 'contact') {
            // Prevzatie so záznamom (CanalClaim) sa dá v lehote napadnúť priamo z e-mailu.
            if ($this->claim?->contest_token && $this->claim->contest_until) {
                return $mail
                    ->line(__('mail.canal_ownership.contest', ['date' => $this->claim->contest_until->format('d. m. Y')]))
                    ->action(
                        __('mail.canal_ownership.contest_action'),
                        rtrim((string) config('app.frontend_url'), '/').'/prevzatie/namietka/'.$this->claim->contest_token,
                    );
            }

            return $mail->line(__('mail.canal_ownership.not_you'));
        }

        return $mail->action(__('mail.canal_ownership.action'), DashboardUrl::base().'/canals/'.$this->canal->id);
    }

    /** `member` (dotknutý), `contact` (adresa kanála bez účtu), inak `owners`. */
    private function audience(object $notifiable): string
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return 'contact';
        }

        return $notifiable instanceof User && $notifiable->is($this->member) ? 'member' : 'owners';
    }
}
