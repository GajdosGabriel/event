<?php

namespace App\Notifications;

use App\Models\Event;
use App\Notifications\Channels\BellChannel;
use App\Services\Tickets\EventSignup;
use App\Support\MailText;
use App\Support\PublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Účastníkovi, keď sa vstupenka zdarma vydať nedala — záujem sme odovzdali
 * organizátorovi (EventSignup). `$reason` hovorí prečo: organizátor rezerváciu
 * neponúka (EventSignup::INTEREST_UNAVAILABLE), alebo je plno či po uzávierke
 * (EventSignup::INTEREST_FULL).
 */
class EventInterestRecorded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Event $event,
        protected ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return [BellChannel::class, 'mail'];
    }

    public function toBell(object $notifiable): array
    {
        return ['message' => __('mail.event_interest.subject', ['event' => MailText::subjectName($this->event->name)]), 'link' => PublicUrl::event($this->event)];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = MailText::name($this->event->name) ?: __('mail.common.event_fallback');
        $reason = $this->reason === EventSignup::INTEREST_FULL ? 'full' : 'unavailable';

        return (new MailMessage)
            ->subject(__('mail.event_interest.subject', ['event' => MailText::subjectName($event)]))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.event_interest.intro_'.$reason, ['event' => $event]))
            ->line(__('mail.event_interest.next'))
            ->action(__('mail.event_interest.action'), PublicUrl::event($this->event));
    }
}
