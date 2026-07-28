<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class PendingRegistrationVerification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $token,
        protected int $ttlHours
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verifyUrl = rtrim(config('app.url'), '/') . '/api/register/verify/' . $this->token;

        return (new MailMessage())
            ->subject(__('mail.verification.subject'))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.verification.intro'))
            ->action(__('mail.verification.action'), $verifyUrl)
            ->line(trans_choice('mail.verification.expires', $this->ttlHours))
            ->line(__('mail.verification.ignore'));
    }
}
