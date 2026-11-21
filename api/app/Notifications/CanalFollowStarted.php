<?php

namespace App\Notifications;

use App\Models\Canal;
use App\Models\Subscription;
use App\Support\MailText;
use App\Support\PublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Uvítanie po tlačidle „Sledovať organizátora" (CanalFollowing::follow).
 *
 * Nie je to potvrdenie, ktoré treba odkliknúť — sledovanie platí hneď. E-mail
 * povie, na čo sa človek prihlásil, a dá mu do ruky odhlásenie; to je zároveň
 * obrana toho, komu adresu zadal niekto iný. Automatické sledovanie po
 * rezervácii či otázke ho nedostáva.
 */
class CanalFollowStarted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Canal $canal,
        protected Subscription $subscription,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.canal_follow.subject', ['canal' => MailText::subjectName($this->canal->name)]))
            ->markdown('mail.canal-follow-started', [
                'greeting' => __('mail.common.greeting'),
                'canalName' => MailText::name($this->canal->name),
                'canalUrl' => PublicUrl::canal($this->canal),
                'unsubscribeUrl' => PublicUrl::unsubscribe((string) $this->subscription->token),
            ]);
    }
}
