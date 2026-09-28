<?php

namespace App\Notifications;

use App\Models\CanalClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-maily procesu prevzatia kanála (App\Services\Canals\CanalClaims).
 *
 * `$kind`:
 *  - `verify`    — kontaktnej adrese kanála: niekto žiada o správu, potvrdiť?
 *  - `requested` — super-adminom: žiadosť čaká na posúdenie,
 *  - `rejected`  — žiadateľovi: žiadosť zamietnutá,
 *  - `contested` — super-adminom: kontaktná adresa prevzatie napadla,
 *  - `reverted`  — žiadateľovi: prevzatie bolo vrátené.
 *
 * Kým platí `canals.simulate_claim_mail`, e-maily sa len zapíšu do denníka.
 */
class CanalClaimNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public const VERIFY = 'verify';
    public const REQUESTED = 'requested';
    public const REJECTED = 'rejected';
    public const CONTESTED = 'contested';
    public const REVERTED = 'reverted';

    public function __construct(
        public readonly CanalClaim $claim,
        public readonly string $kind,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $claim = $this->claim->loadMissing(['canal', 'user']);
        $canal = $claim->canal?->name ?: __('mail.canal_invitation.canal_fallback');
        $key = 'mail.canal_claim.' . $this->kind;
        $params = [
            'canal' => $canal,
            'member' => $claim->user?->maskedEmail() ?? '—',
            'name' => $claim->user?->displayName() ?? '—',
        ];

        $mail = (new MailMessage)
            ->subject(__($key . '.subject', $params))
            ->greeting(__('mail.common.greeting'))
            ->line(__($key . '.intro', $params));

        if ($claim->message && in_array($this->kind, [self::REQUESTED, self::VERIFY], true)) {
            $mail->line(__('mail.canal_claim.message', ['message' => $claim->message]));
        }

        if ($claim->contest_note && $this->kind === self::CONTESTED) {
            $mail->line(__('mail.canal_claim.message', ['message' => $claim->contest_note]));
        }

        if ($claim->decision_note && in_array($this->kind, [self::REJECTED, self::REVERTED], true)) {
            $mail->line(__('mail.canal_claim.message', ['message' => $claim->decision_note]));
        }

        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return match ($this->kind) {
            self::VERIFY => $mail
                ->action(__($key . '.action'), $frontend . '/prevzatie/' . $claim->token)
                ->line(__($key . '.ignore', ['date' => $claim->expires_at?->format('d. m. Y') ?? ''])),
            self::REQUESTED, self::CONTESTED => $mail
                ->action(__($key . '.action'), $frontend . '/admin/prevzatia'),
            default => $mail,
        };
    }
}
