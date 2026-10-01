<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * „Používateľ napísal podpore." Ide super-adminom — do zvončeka aj e-mailom.
 */
class SupportTicketCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SupportTicket $ticket) {}

    public function via(object $notifiable): array
    {
        return [Channels\BellChannel::class, 'mail'];
    }

    public function toBell(object $notifiable): array
    {
        return [
            'message' => $this->subject(),
            'link' => '/admin/podpora/'.$this->ticket->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // ShouldQueue: na workeri je model čerstvo načítaný bez relácií.
        $ticket = $this->ticket->loadMissing(['user', 'canal']);
        $first = $ticket->messages()->first();

        return (new MailMessage)
            ->subject($this->subject())
            ->greeting(__('mail.common.greeting'))
            ->line(__('support.mail.created.intro', [
                'name' => $ticket->user?->displayName() ?? '—',
                'category' => $ticket->category->label(),
                'context' => $ticket->canal?->name ?? __('support.no_context'),
            ]))
            ->line(Str::limit((string) $first?->body, 1000))
            ->action(__('support.mail.action'), $this->url());
    }

    private function subject(): string
    {
        return __('support.mail.created.subject', [
            'reference' => $this->ticket->reference(),
            'subject' => $this->ticket->subject,
        ]);
    }

    private function url(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/admin/podpora/'.$this->ticket->id;
    }
}
