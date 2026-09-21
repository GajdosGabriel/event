<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingRegistrationVerification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string|null  $eventName  Podujatie, na ktoré sa človek registráciou
     *                                  prihlasuje — miesto mu rezervujeme až po overení.
     */
    public function __construct(
        protected string $token,
        protected int $ttlHours,
        protected ?string $eventName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Stránka vo fronte overí token cez API a ukáže výsledok aj s rezerváciou.
        $verifyUrl = rtrim((string) config('app.frontend_url'), '/').'/verify-email/'.$this->token;

        if ($this->eventName === null) {
            return (new MailMessage)
                ->subject(__('mail.verification.subject'))
                ->greeting(__('mail.common.greeting'))
                ->line(__('mail.verification.intro'))
                ->action(__('mail.verification.action'), $verifyUrl)
                ->line(trans_choice('mail.verification.expires', $this->ttlHours))
                ->line(__('mail.verification.ignore'));
        }

        return (new MailMessage)
            ->subject(__('mail.verification.subject_event', ['event' => $this->eventName]))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.verification.intro_event', ['event' => $this->eventName]))
            ->line(__('mail.verification.event_note', ['event' => $this->eventName]))
            ->action(__('mail.verification.action_event'), $verifyUrl)
            ->line(trans_choice('mail.verification.expires', $this->ttlHours))
            ->line(__('mail.verification.ignore'));
    }
}
