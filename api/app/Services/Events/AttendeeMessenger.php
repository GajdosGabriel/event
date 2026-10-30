<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventMessage;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\EventAnnouncement;
use App\Notifications\TicketCancelledByOrganizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Odoslanie správ organizátora účastníkom vrátane záznamu do histórie.
 * Komu správa ide, rozhoduje AttendeeDirectory.
 */
class AttendeeMessenger
{
    /**
     * @param  Collection<string, array{email: string, name: string}>  $recipients
     */
    public function announce(Event $event, ?User $sender, string $subject, string $body, Collection $recipients, string $audience): void
    {
        foreach ($recipients as $recipient) {
            Notification::route('mail', $recipient['email'])->notify(new EventAnnouncement(
                $event,
                $subject,
                $body,
                $sender?->email,
                $sender?->displayName(),
            ));
        }

        $this->log($event, $sender, EventMessage::KIND_ANNOUNCEMENT, $audience, $subject, $body, $recipients->pluck('email')->values()->all());
    }

    public function cancellation(Ticket $ticket, ?User $sender, ?string $reason): void
    {
        $event = $ticket->event()->first();

        if (! $event || ! filter_var($ticket->holder_email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        Notification::route('mail', $ticket->holder_email)->notify(new TicketCancelledByOrganizer(
            $event,
            $reason,
            $sender?->email,
            $sender?->displayName(),
        ));

        $this->log(
            $event,
            $sender,
            EventMessage::KIND_CANCELLATION,
            'selected',
            TicketCancelledByOrganizer::subjectFor((string) $event->name),
            $reason,
            [$ticket->holder_email],
        );
    }

    /** @param  array<int, string>  $emails */
    private function log(Event $event, ?User $sender, string $kind, string $audience, string $subject, ?string $body, array $emails): void
    {
        EventMessage::query()->create([
            'event_id' => $event->id,
            'user_id' => $sender?->id,
            'kind' => $kind,
            'audience' => $audience,
            'subject' => mb_substr($subject, 0, 190),
            'body' => $body,
            'recipients' => $emails,
        ]);
    }
}
