<?php

namespace App\Notifications;

use App\Models\CanalInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pozvánka do tímu kanála. Odkaz vedie do frontendu, kde sa pozvaný prihlási
 * (alebo zaregistruje) a pozvánku prijme — samotný odkaz členstvo nevytvára.
 */
class CanalInvitationSent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected CanalInvitation $invitation,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $canalName = $this->invitation->canal?->name ?? __('mail.canal_invitation.canal_fallback');
        $inviterName = $this->invitation->invitedBy?->canal?->name;
        $url = rtrim((string) config('app.frontend_url'), '/') . '/pozvanka/' . $this->invitation->token;

        $mail = (new MailMessage())
            ->subject(__('mail.canal_invitation.subject', ['canal' => $canalName]))
            ->greeting(__('mail.common.greeting'))
            ->line($inviterName
                ? __('mail.canal_invitation.intro_named', ['inviter' => $inviterName, 'canal' => $canalName])
                : __('mail.canal_invitation.intro', ['canal' => $canalName]))
            ->line(__('mail.canal_invitation.role', ['role' => $this->invitation->role->label()]))
            ->line(__('mail.canal_invitation.role_note.' . $this->invitation->role->value))
            ->action(__('mail.canal_invitation.action'), $url);

        if ($this->invitation->expires_at !== null) {
            $mail->line(__('mail.canal_invitation.expires', [
                'date' => $this->invitation->expires_at->format('d. m. Y'),
            ]));
        }

        return $mail
            ->line(__('mail.canal_invitation.email_note', ['email' => $this->invitation->email]))
            ->line(__('mail.canal_invitation.ignore'));
    }
}
