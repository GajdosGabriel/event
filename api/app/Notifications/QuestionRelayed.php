<?php

namespace App\Notifications;

use App\Models\Canal;
use App\Models\Event;
use App\Support\PublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * Otázka návštevníka na podujatie kanála bez správcu (QuestionRelay).
 *
 *  - `organizer` — na overenú adresu kanála; Reply-To je overený e-mail
 *    pisateľa, takže odpoveď mu príde priamo. Nevyžiadaný e-mail → odhlásenie
 *    jedným klikom (text aj List-Unsubscribe) a ponuka prevziať profil.
 *  - `admin` — kópia správcovi portálu: kto sa pýta, koho a čo.
 */
class QuestionRelayed extends Notification implements ShouldQueue
{
    use Queueable;

    public const ORGANIZER = 'organizer';
    public const ADMIN = 'admin';

    public function __construct(
        public readonly Event $event,
        public readonly Canal $canal,
        public readonly string $audience,
        public readonly string $body,
        public readonly string $askerName,
        public readonly string $askerEmail,
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
            'name' => $this->askerName,
            'email' => $this->askerEmail,
            'site' => (string) config('app.name'),
        ];
        $key = 'mail.question_relayed.' . $this->audience;

        $mail = (new MailMessage)
            ->subject(__($key . '.subject', $params))
            ->greeting(__('mail.common.greeting'))
            ->line(__($key . '.intro', $params))
            ->line('> ' . str_replace("\n", "\n> ", $this->body));

        if ($this->audience === self::ADMIN) {
            return $mail
                ->line(__($key . '.sent_to', ['email' => (string) $this->canal->email]))
                ->action(__($key . '.action'), PublicUrl::event($this->event));
        }

        $unsubscribe = $this->unsubscribeUrl;

        return $mail
            ->replyTo($this->askerEmail, $this->askerName)
            ->line(__($key . '.reply', $params))
            ->line(__($key . '.offer', $params))
            ->action(__($key . '.action'), $this->claimUrl)
            ->line(__($key . '.why', $params))
            ->line(__($key . '.unsubscribe', ['url' => $unsubscribe]))
            ->withSymfonyMessage(function (Email $message) use ($unsubscribe) {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $unsubscribe . '>');
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }
}
