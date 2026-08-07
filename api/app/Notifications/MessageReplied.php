<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Odpoveď organizátora na správu z „Poslať správu" — protistrana k
 * {@see MessageReceived}. Reply-to smeruje na organizátora, takže konverzácia
 * sa dá dokončiť aj v e-maile bez toho, aby si niekto musel zakladať účet.
 */
class MessageReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Message $reply,
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
        // ShouldQueue: na workeri je model čerstvo načítaný bez relácií, preto
        // loadMissing (Model::preventLazyLoading je mimo produkcie zapnutý).
        $target = $this->reply->loadMissing('messageable')->messageable;
        $targetName = $target?->name ?? __('mail.message_received.target_fallback');
        $label = __('mail.message_received.targets.' . ($this->reply->targetType() ?? 'default'));

        return (new MailMessage())
            ->subject(__('mail.message_replied.subject', ['label' => $label, 'name' => $targetName]))
            ->replyTo($this->senderEmail, $this->senderName)
            ->markdown('mail.message-replied', [
                'label' => $label,
                'targetName' => $targetName,
                'senderName' => $this->senderName,
                'body' => $this->reply->body,
                'inboxUrl' => rtrim((string) config('app.frontend_url'), '/') . '/dashboard/spravy',
            ]);
    }
}
