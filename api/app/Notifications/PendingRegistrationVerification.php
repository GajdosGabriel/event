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
            ->subject('Verify your email')
            ->greeting('Hello!')
            ->line('Thanks for registering. Verify your email to finish your registration.')
            ->action('Verify Email', $verifyUrl)
            ->line("This link will expire in {$this->ttlHours} hours.")
            ->line('If you did not create this account, no action is required.');
    }
}
