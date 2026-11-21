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
 * Prvý e-mail po „Sledovať organizátora".
 *
 * Nepotvrdený odber (hosť) dostane tlačidlo „Potvrdiť" — bez kliknutia mu
 * nepríde nič ďalšie (dvojité potvrdenie, CanalSubscriptionController).
 * Prihlásený s overenou adresou má odber platný hneď a e-mail mu len povie,
 * na čo sa prihlásil. Odhlásenie je v oboch.
 */
class CanalFollowConfirmation extends Notification implements ShouldQueue
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
        // Na workeri sa model načíta odznova — stav potvrdenia je aktuálny.
        $pending = $this->subscription->confirmed_at === null;
        $name = MailText::name($this->canal->name);

        return (new MailMessage)
            ->subject(__($pending ? 'mail.canal_follow.subject_pending' : 'mail.canal_follow.subject_confirmed', ['canal' => MailText::subjectName($this->canal->name)]))
            ->markdown('mail.canal-follow-confirmation', [
                'greeting' => __('mail.common.greeting'),
                'pending' => $pending,
                'canalName' => $name,
                'actionUrl' => $pending
                    ? PublicUrl::confirmSubscription((string) $this->subscription->token)
                    : PublicUrl::canal($this->canal),
                'unsubscribeUrl' => PublicUrl::unsubscribe((string) $this->subscription->token),
            ]);
    }
}
