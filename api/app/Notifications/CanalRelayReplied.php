<?php

namespace App\Notifications;

use App\Models\CanalRelayThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Odpoveď organizátora pisateľovi (CanalRelay::forwardReply). Ďalšia odpoveď
 * pisateľa už ide organizátorovi priamo cez Reply-To.
 */
class CanalRelayReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly CanalRelayThread $thread,
        public readonly string $reply,
        public readonly string $fromEmail,
        public readonly ?string $fromName,
        public readonly bool $hasAttachments,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $canal = $this->thread->canal?->name ?? __('mail.canal_invitation.canal_fallback');
        $params = ['target' => $this->thread->target_name, 'canal' => $canal];

        $mail = (new MailMessage)
            ->subject(__('mail.canal_relay_replied.subject', $params))
            ->greeting(__('mail.common.greeting'))
            ->replyTo($this->fromEmail, $this->fromName ?: $canal)
            ->line(__('mail.canal_relay_replied.intro', $params))
            ->line('> '.str_replace("\n", "\n> ", $this->reply));

        if ($this->hasAttachments) {
            $mail->line(__('mail.canal_relay_replied.attachments'));
        }

        return $mail->line(__('mail.canal_relay_replied.outro'));
    }
}
