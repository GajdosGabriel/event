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
 * „Niečo sa zmenilo." Presne to, čo sme odberateľovi sľúbili na tlačidle — a
 * dôvod, prečo nám adresu vôbec dal. Pripomienka je bonus, toto je služba.
 *
 * Pre organizátora je to zároveň jediný spôsob, ako sa presun do inej sály
 * dostane k ľuďom, ktorí sa nikde neregistrovali. Pri bezplatnom podujatí to
 * je každý, kto príde.
 *
 * `$changes` sú hotové vety, nie kódy — čo sa zmenilo, vie posúdiť len miesto,
 * ktoré porovnávalo staré a nové hodnoty (EventObserver), a notifikácia by to
 * musela zisťovať znova z už uloženého modelu.
 */
class EventChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $changes
     */
    public function __construct(
        protected Event $event,
        protected Subscription $subscription,
        protected array $changes,
        protected bool $cancelled = false,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $venue = $this->event->loadMissing('venue')->venue;

        $calendar = new EventCalendarLinks($this->event);

        $mail = (new MailMessage())
            ->subject(
                $this->cancelled
                    ? __('mail.event_changed.subject_cancelled', ['event' => $this->event->name])
                    : __('mail.event_changed.subject', ['event' => $this->event->name])
            )
            ->markdown('mail.event-changed', [
                'greeting' => __('mail.common.greeting'),
                'eventName' => $this->event->name,
                'eventUrl' => PublicUrl::event($this->event),
                'cancelled' => $this->cancelled,
                'changes' => $this->changes,
                'startsAt' => $this->event->start_at?->format('d. m. Y H:i'),
                'venueName' => $venue?->name,
                'unsubscribeUrl' => PublicUrl::unsubscribe((string) $this->subscription->token),
                // Zrušené podujatie nemá čo ponúkať do kalendára — naopak, ten
                // záznam si má človek zmazať.
                ...($this->cancelled ? $this->emptyCalendar() : $calendar->viewData()),
            ]);

        return $this->cancelled ? $mail : $calendar->attachTo($mail);
    }

    /** @return array<string, null> */
    private function emptyCalendar(): array
    {
        return ['calendarUrl' => null, 'googleUrl' => null, 'outlookUrl' => null];
    }
}
