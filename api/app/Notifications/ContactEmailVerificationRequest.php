<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Žiadosť o potvrdenie kontaktného e-mailu kanála / miesta / podujatia / firmy.
 *
 * Chodí na zadanú adresu, nie na účet organizátora — overuje sa práve to, že
 * adresa niekomu naozaj patrí a číta ju. Odkaz vedie do frontendu, kde sa
 * potvrdenie odošle do API (viď ContactEmailVerificationController).
 */
class ContactEmailVerificationRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $token,
        protected int $ttlHours,
        protected string $subjectName,
        protected string $subjectType,
        protected string $email,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/') . '/overenie-emailu/' . $this->token;

        $name = trim($this->subjectName);

        return (new MailMessage())
            ->subject(__('mail.contact_email_verification.subject', ['type' => $this->subjectType]))
            ->greeting(__('mail.common.greeting'))
            ->line($name !== ''
                ? __('mail.contact_email_verification.intro_named', ['type' => $this->subjectType, 'name' => $name])
                : __('mail.contact_email_verification.intro', ['type' => $this->subjectType]))
            ->line(__('mail.contact_email_verification.why'))
            ->action(__('mail.contact_email_verification.action'), $url)
            ->line(trans_choice('mail.contact_email_verification.expires', $this->ttlHours))
            ->line(__('mail.contact_email_verification.ignore'));
    }
}
