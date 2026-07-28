<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Správa, ktorú návštevník poslal vlastníkovi cieľa (podujatie / miesto /
 * kanál) cez tlačidlo „Poslať správu". Odpovedať sa dá priamo — reply-to
 * smeruje na odosielateľa.
 */
class MessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Message $message,
        protected string $senderName,
        protected string $senderEmail,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $target = $this->message->messageable;
        $targetName = $target?->name ?? __('mail.message_received.target_fallback');
        $label = $this->targetLabel();
        $targetUrl = $this->targetUrl();

        return (new MailMessage())
            ->subject(__('mail.message_received.subject', ['label' => $label, 'name' => $targetName]))
            // Vlastník vie odpovedať priamo odosielateľovi.
            ->replyTo($this->senderEmail, $this->senderName)
            ->markdown('mail.message-received', [
                'label' => $label,
                'targetName' => $targetName,
                'targetUrl' => $targetUrl,
                'senderName' => $this->senderName,
                'senderEmail' => $this->senderEmail,
                'body' => $this->message->body,
            ]);
    }

    /** Preložený názov typu cieľa pre predmet a text e-mailu. */
    private function targetLabel(): string
    {
        $type = match ($this->message->targetType()) {
            'event', 'venue', 'canal' => $this->message->targetType(),
            default => 'default',
        };

        return __('mail.message_received.targets.' . $type);
    }

    /** Odkaz na cieľ vo frontende podľa jeho typu. */
    private function targetUrl(): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $path = match ($this->message->targetType()) {
            'event' => '/events/',
            'venue' => '/venues/',
            'canal' => '/canals/',
            default => '/',
        };

        return $base . $path . $this->message->messageable_id;
    }
}
