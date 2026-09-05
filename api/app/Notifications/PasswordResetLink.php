<?php

namespace App\Notifications;

use App\Support\PublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Odkaz na nastavenie nového hesla.
 *
 * Nahrádza Laravelovu `ResetPassword`, lebo tá vedie na routu v API
 * (`password.reset`), ktorú tu nemáme — formulár je stránka SPA. Adresu
 * skladá [PublicUrl::passwordReset()], rovnako ako pri odhlásení z odberu.
 *
 * E-mail v odkaze nie je autorizácia, len predvyplnenie poľa; token bez
 * zodpovedajúcej adresy broker neuzná.
 */
class PasswordResetLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $token,
        protected string $email
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // `expire` je v minútach (config/auth.php), v e-maile chceme hodiny.
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage())
            ->subject(__('mail.password_reset.subject'))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.password_reset.intro'))
            ->action(__('mail.password_reset.action'), PublicUrl::passwordReset($this->token, $this->email))
            ->line(trans_choice('mail.password_reset.expires', $minutes))
            ->line(__('mail.password_reset.ignore'));
    }
}
