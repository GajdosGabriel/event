<?php

namespace App\Notifications;

use App\Models\Canal;
use App\Models\Event;
use App\Models\Subscription;
use App\Support\MailText;
use App\Support\PublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Súhrn nových akcií kanála pre jeho sledovateľa (app:canal-followers-notify).
 *
 * Vždy jeden e-mail za kanál a beh, nikdy jeden za akciu — import vie
 * zverejniť desiatky akcií naraz a za každú zvlášť by sa sledovatelia hneď
 * odhlásili.
 */
class CanalNewEvents extends Notification implements ShouldQueue
{
    use Queueable;

    /** Viac sa do e-mailu nevojde tak, aby ho niekto dočítal. */
    public const MAX_LISTED = 10;

    /**
     * @param  array<int, int>  $eventIds
     */
    public function __construct(
        protected Canal $canal,
        protected array $eventIds,
        protected Subscription $subscription,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $events = Event::query()
            ->whereIn('id', $this->eventIds)
            ->with('venue')
            ->orderByRaw('start_at IS NULL')
            ->orderBy('start_at')
            ->get();

        $count = $events->count();
        $canalName = MailText::subjectName($this->canal->name);

        $subject = $count === 1
            ? __('mail.canal_new_events.subject_one', ['canal' => $canalName, 'event' => MailText::subjectName((string) $events->first()?->name)])
            : __('mail.canal_new_events.subject_many', ['canal' => $canalName, 'count' => $count]);

        return (new MailMessage)
            ->subject($subject)
            ->markdown('mail.canal-new-events', [
                'greeting' => __('mail.common.greeting'),
                'canalName' => MailText::name($this->canal->name),
                'canalUrl' => PublicUrl::canal($this->canal),
                'events' => $events->take(self::MAX_LISTED)->map(fn (Event $event) => [
                    'name' => MailText::name($event->name),
                    'url' => PublicUrl::event($event),
                    'when' => MailText::range($event->start_at, $event->end_at),
                    'where' => $event->getRelation('venue')?->name ?: $event->location_name,
                ])->all(),
                'more' => max(0, $count - self::MAX_LISTED),
                'unsubscribeUrl' => PublicUrl::unsubscribe((string) $this->subscription->token),
            ]);
    }
}
