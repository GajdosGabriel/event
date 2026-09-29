<?php

namespace App\Notifications;

use App\Models\SupportMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Nová správa vo vlákne podpory.
 *
 * Smer určuje text aj odkaz: používateľ dostane „podpora odpovedala" s odkazom
 * do Správ, podpora „používateľ doplnil" s odkazom do administrácie.
 * Kto napísal, ten upozornenie nedostane.
 */
class SupportTicketReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SupportMessage $message) {}

    public function via(object $notifiable): array
    {
        return [Channels\BellChannel::class, 'mail'];
    }

    public function toBell(object $notifiable): array
    {
        return ['message' => $this->subject(), 'link' => $this->path()];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->message->loadMissing('author');
        // Používateľ vidí podporu ako „Podpora", nie konkrétneho admina.
        $author = $message->is_staff
            ? __('support.staff_name')
            : ($message->author?->displayName() ?? '—');

        return (new MailMessage)
            ->subject($this->subject())
            ->greeting(__('mail.common.greeting'))
            ->line(__('support.mail.replied.intro', ['name' => $author]))
            ->line(Str::limit($message->body, 1000))
            ->action(__('support.mail.action'), rtrim((string) config('app.frontend_url'), '/') . $this->path())
            ->line(__('support.mail.replied.outro'));
    }

    private function subject(): string
    {
        $ticket = $this->message->loadMissing('ticket')->ticket;

        return __(
            $this->message->is_staff ? 'support.mail.replied.subject_answered' : 'support.mail.replied.subject_user',
            ['reference' => $ticket->reference(), 'subject' => $ticket->subject]
        );
    }

    private function path(): string
    {
        return ($this->message->is_staff ? '/dashboard/spravy/podpora/' : '/admin/podpora/')
            . $this->message->support_ticket_id;
    }
}
