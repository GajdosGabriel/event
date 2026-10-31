<?php

namespace App\Notifications;

use App\Models\Canal;
use App\Notifications\Concerns\SendsViaAccount;
use App\Services\Canals\CanalRelay;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * Text návštevníka preposlaný kanálu bez správcu (CanalRelay).
 *
 *  - `organizer` — na kontakt kanála, s ponukou prevziať profil a odhlásením.
 *    Ide cez Account kvôli nedoručeniam. Odpoveď príde do Accountu a odtiaľ ju
 *    webhook prepošle pisateľovi (CanalRelay::forwardReply), preto jeho adresa
 *    v texte nie je; Reply-To platí len pri SMTP bez Accountu.
 *  - `admin` — kópia správcovi portálu: kto píše, komu a čo.
 */
abstract class RelayedToCanal extends Notification implements ShouldQueue
{
    use Queueable, SendsViaAccount;

    public const ORGANIZER = 'organizer';

    public const ADMIN = 'admin';

    /** Záznam CanalRelayThread — z neho vznikne samostatná konverzácia v Accounte. */
    public ?int $threadId = null;

    public function __construct(
        public readonly Canal $canal,
        public readonly string $audience,
        public readonly string $body,
        public readonly string $askerName,
        public readonly string $askerEmail,
        public readonly string $claimUrl,
        public readonly string $unsubscribeUrl,
        public readonly string $organizerEmail,
    ) {}

    /** Kľúč prekladu, napr. `mail.question_relayed`. */
    abstract protected function textKey(): string;

    /** @return array<string, string> */
    abstract protected function targetParams(): array;

    abstract protected function targetUrl(): string;

    abstract public function accountKind(): string;

    public function via(object $notifiable): array
    {
        return $this->mailVia($notifiable);
    }

    public function forThread(int $threadId): static
    {
        $this->threadId = $threadId;

        return $this;
    }

    public function accountRef(object $notifiable): array
    {
        $ref = $this->canalRef($this->canal);

        return $this->threadId === null
            ? $ref
            : [...$ref, 'type' => CanalRelay::ACCOUNT_REF, 'id' => (string) $this->threadId];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $params = [
            ...$this->targetParams(),
            'canal' => $this->canal->name,
            'name' => $this->askerName,
            'email' => $this->askerEmail,
            'site' => (string) config('app.name'),
        ];
        $key = $this->textKey().'.'.$this->audience;
        $shared = 'mail.question_relayed.'.$this->audience;

        $mail = (new MailMessage)
            ->subject(__($key.'.subject', $params))
            ->greeting(__('mail.common.greeting'))
            ->line(__($key.'.intro', $params))
            ->line('> '.str_replace("\n", "\n> ", $this->body));

        if ($this->audience === self::ADMIN) {
            return $mail
                ->line(__($key.'.sent_to', ['email' => $this->organizerEmail]))
                ->action(__($key.'.action'), $this->targetUrl());
        }

        $unsubscribe = $this->unsubscribeUrl;

        return $mail
            ->replyTo($this->askerEmail, $this->askerName)
            ->line(__($shared.'.reply', $params))
            ->line(__($key.'.offer', $params))
            ->action(__($shared.'.action'), $this->claimUrl)
            ->line(__($shared.'.why', $params))
            ->line(__($shared.'.unsubscribe', ['url' => $unsubscribe]))
            ->withSymfonyMessage(function (Email $message) use ($unsubscribe) {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>');
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }
}
