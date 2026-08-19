<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\Subscription;
use App\Services\Calendar\EventCalendarLinks;
use App\Support\PublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prvý (a pri nezmenenom podujatí aj jediný) e-mail po prihlásení k odberu.
 *
 * Nie je to potvrdenie v zmysle double opt-inu — odber platí hneď. Plní tri
 * úlohy naraz: povie, na čo sa človek prihlásil, dá mu rovno termín do
 * kalendára (a kalendár si ho vďaka VALARM pripomenie sám, aj keby náš e-mail
 * neprišiel), a hlavne dá do rúk odkaz na odhlásenie. Ten je aj jediná obrana
 * niekoho, komu adresu zadal cudzí človek.
 */
class SubscriptionConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Event $event,
        protected Subscription $subscription,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Notifikácia je ShouldQueue — na workeri sa model načíta odznova a bez
        // relácie (Model::preventLazyLoading je mimo produkcie zapnuté).
        $venue = $this->event->loadMissing('venue')->venue;

        $calendar = new EventCalendarLinks($this->event);

        $mail = (new MailMessage())
            ->subject(__('mail.subscription_confirmed.subject', ['event' => $this->event->name]))
            ->markdown('mail.subscription-confirmed', [
                'greeting' => __('mail.common.greeting'),
                'eventName' => $this->event->name,
                'eventUrl' => PublicUrl::event($this->event),
                'startsAt' => $this->event->start_at?->format('d. m. Y H:i'),
                'venueName' => $venue?->name,
                'venueAddress' => trim((string) $venue?->street),
                'unsubscribeUrl' => PublicUrl::unsubscribe((string) $this->subscription->token),
                ...$calendar->viewData(),
            ]);

        return $calendar->attachTo($mail);
    }
}
