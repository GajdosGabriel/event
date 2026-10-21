<?php

namespace App\Services\Account;

use App\Models\Canal;
use App\Models\CanalEmail;
use App\Services\Canals\CanalEmails;
use App\Services\SystemLog\Recorder;

/**
 * Udalosti o e-mailoch z Accountu (`message.*`).
 *
 * Event posiela cez Account maily kanálom; odpoveď alebo nedoručenie sa
 * vracia sem a zapíše sa do `canal_emails` – presne to, čo sa doteraz
 * zadávalo ručne príkazom `app:canal-email`.
 *
 * Vec, ktorá medzičasom zmizla (zmazaný kanál), sa ticho preskočí:
 * Account by inak doručenie opakoval celé hodiny.
 */
class AccountMessages
{
    public function __construct(private readonly CanalEmails $emails) {}

    /**
     * @param  array<string, mixed>  $conversation
     * @param  array<string, mixed>  $message
     */
    public function handle(string $event, array $conversation, array $message): void
    {
        if (($conversation['ref']['type'] ?? null) !== 'canal') {
            return;
        }

        $canal = Canal::find($conversation['ref']['id'] ?? null);

        if (! $canal) {
            return;
        }

        $contact = CanalEmails::normalize($conversation['contact_email'] ?? null);

        match ($event) {
            'message.received' => $this->received($canal, $contact, $message),
            'message.bounced' => $this->bounced($canal, $contact, $message),
            default => null,
        };
    }

    /** @param array<string, mixed> $message */
    private function received(Canal $canal, ?string $contact, array $message): void
    {
        $from = CanalEmails::normalize($message['from'] ?? null);

        // Odpoveď z adresy, na ktorú sme písali, ju potvrdzuje. Keď odpíše
        // kolega z inej adresy, tú len zaznamenáme – kanálu ju nepridávame.
        if ($from !== null && $from === $contact) {
            $this->emails->recordReply($from);
        }

        Recorder::info('canals', 'email_replied', "Kanál {$canal->name}: odpoveď od {$from}",
            status: 'replied',
            recipient: $from,
            subject: $canal,
            context: ['message' => $message['id'] ?? null, 'kind' => $message['kind'] ?? null],
        );
    }

    /** @param array<string, mixed> $message */
    private function bounced(Canal $canal, ?string $contact, array $message): void
    {
        $address = CanalEmails::normalize($message['to'] ?? null) ?? $contact;

        $this->emails->recordBounce(
            $address,
            ($message['bounce_type'] ?? 'hard') === 'soft' ? CanalEmail::BOUNCE_SOFT : CanalEmail::BOUNCE_HARD,
            $message['error'] ?? null,
        );
    }
}
