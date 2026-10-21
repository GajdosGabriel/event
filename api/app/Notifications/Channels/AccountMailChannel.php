<?php

namespace App\Notifications\Channels;

use App\Services\Account\AccountClient;
use App\Services\Account\AccountMessageRejected;
use App\Services\SystemLog\Recorder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * E-mail cez schránku Eventu v Accounte namiesto lokálneho SMTP.
 *
 * Notifikácia zostáva, ako bola (`toMail()` so šablónou a prekladmi) a
 * navyše povie, o ktorom kanáli píše (`accountRef()`). Odpoveď klienta
 * príde späť webhookom – pozri App\Listeners\AccountMessageListener.
 *
 * Do denníka (system_logs) sa zapisuje sám, lebo lokálne udalosti
 * MessageSending/MessageSent pri tejto ceste nenastanú.
 */
class AccountMailChannel
{
    /** Hlavičky, ktoré Account prijme (odhlásenie jedným klikom). */
    private const HEADERS = ['List-Unsubscribe', 'List-Unsubscribe-Post'];

    public function __construct(private readonly AccountClient $account) {}

    /** @return array<string, mixed>|null */
    public function send(object $notifiable, Notification $notification): ?array
    {
        if (! method_exists($notification, 'toMail') || ! method_exists($notification, 'accountRef')) {
            throw new \LogicException($notification::class.' musí mať toMail() aj accountRef().');
        }

        $to = $notifiable instanceof AnonymousNotifiable
            ? ($notifiable->routes['mail'] ?? null)
            : $notifiable->routeNotificationFor('mail', $notification);

        if (! is_string($to) || $to === '') {
            return null;
        }

        /** @var MailMessage $mail */
        $mail = $notification->toMail($notifiable);
        $subject = (string) ($mail->subject ?: config('app.name'));

        $payload = array_filter([
            'to' => $to,
            'subject' => $subject,
            'html' => (string) $mail->render(),
            'ref' => $notification->accountRef($notifiable),
            'kind' => method_exists($notification, 'accountKind') ? $notification->accountKind() : null,
            'meta' => ['notification' => class_basename($notification)],
            'headers' => $this->headers($mail),
        ], fn ($value) => $value !== null && $value !== []);

        try {
            $result = $this->account->sendMessage($payload);
        } catch (AccountMessageRejected $e) {
            // Opakovanie nepomôže – zapíšeme a frontu nezasekneme.
            Recorder::warning('mail', 'rejected', $subject,
                status: $e->reason,
                recipient: $to,
                context: ['class' => $notification::class, 'via' => 'account', 'error' => $e->getMessage()],
            );

            return null;
        }

        Recorder::info('mail', 'sent', $subject,
            status: 'sent',
            recipient: $to,
            context: [
                'class' => $notification::class,
                'via' => 'account',
                'conversation' => $result['conversation']['id'] ?? null,
                'message' => $result['message']['id'] ?? null,
            ],
        );

        return $result;
    }

    /** @return array<string, string> */
    private function headers(MailMessage $mail): array
    {
        if ($mail->callbacks === []) {
            return [];
        }

        $email = new Email;

        foreach ($mail->callbacks as $callback) {
            $callback($email);
        }

        $headers = [];

        foreach (self::HEADERS as $name) {
            if ($email->getHeaders()->has($name)) {
                $headers[$name] = $email->getHeaders()->get($name)->getBodyAsString();
            }
        }

        return $headers;
    }
}
