<?php

namespace App\Notifications;

use App\Models\Canal;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * Oslovenie organizátora po akcii (App\Services\Canals\CanalOutreachSender):
 * koľko ľudí akciu videlo a ponuka prevziať profil. Nevyžiadaný e-mail —
 * preto odhlásenie jedným klikom v texte aj v hlavičke List-Unsubscribe.
 */
class CanalOutreachNotice extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<string, int>  $stats */
    public function __construct(
        public readonly Canal $canal,
        public readonly Event $event,
        public readonly array $stats,
        public readonly string $claimUrl,
        public readonly string $unsubscribeUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $params = [
            'event' => $this->event->name ?: __('mail.common.event_fallback'),
            'canal' => $this->canal->name,
            'date' => ($this->event->start_at ?? $this->event->end_at)?->format('d. m. Y') ?? '',
            'site' => (string) config('app.name'),
            'views' => $this->stats['event_views'] ?? 0,
            'visitors' => $this->stats['event_visitors'] ?? 0,
            'signups' => $this->stats['signups'] ?? 0,
        ];

        $mail = (new MailMessage)
            ->subject(__('mail.canal_outreach.subject', $params))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.canal_outreach.intro', $params))
            ->line(trans_choice('mail.canal_outreach.views', $params['views'], $params));

        if ($params['signups'] > 0) {
            $mail->line(trans_choice('mail.canal_outreach.signups', $params['signups'], $params));
        }

        $unsubscribe = $this->unsubscribeUrl;

        return $mail
            ->line(__('mail.canal_outreach.offer', $params))
            ->action(__('mail.canal_outreach.action'), $this->claimUrl)
            ->line(__('mail.canal_outreach.why', $params))
            ->line(__('mail.canal_outreach.unsubscribe', ['url' => $unsubscribe]))
            ->withSymfonyMessage(function (Email $message) use ($unsubscribe) {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $unsubscribe . '>');
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }
}
