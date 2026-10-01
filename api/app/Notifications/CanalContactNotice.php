<?php

namespace App\Notifications;

use App\Models\Canal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Zmena kontaktného e-mailu kanála (App\Services\Canals\CanalContactVerifier).
 *
 *  - `verify`  — na novú adresu: potvrďte, že patrí kanálu,
 *  - `changed` — na pôvodnú adresu: kontakt kanála bol zmenený.
 */
class CanalContactNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public const VERIFY = 'verify';

    public const CHANGED = 'changed';

    public function __construct(
        public readonly Canal $canal,
        public readonly string $kind,
        public readonly ?string $newEmail = null,
        public readonly ?string $verifyUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $params = [
            'canal' => $this->canal->name ?: __('mail.canal_invitation.canal_fallback'),
            'email' => $this->newEmail ?? '',
        ];
        $key = 'mail.canal_contact.'.$this->kind;

        $mail = (new MailMessage)
            ->subject(__($key.'.subject', $params))
            ->greeting(__('mail.common.greeting'))
            ->line(__($key.'.intro', $params));

        return $this->kind === self::VERIFY && $this->verifyUrl
            ? $mail->action(__($key.'.action'), $this->verifyUrl)->line(__($key.'.ignore'))
            : $mail->line(__('mail.canal_ownership.not_you'));
    }
}
