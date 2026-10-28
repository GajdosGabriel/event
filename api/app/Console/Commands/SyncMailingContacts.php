<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Models\CanalOutreach;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Account\AccountClient;
use App\Services\Account\AccountMailing;
use Illuminate\Console\Command;

/**
 * Jednorazovo doplní adresár v Accounte: používatelia, účastníci s platnou
 * registráciou a oslovené kontakty kanálov. Ďalej sa adresár udržiava sám
 * (SyncMailingContact, ReportMailingEvent). Opakovaný beh je bezpečný –
 * štítky a atribúty sa len prepíšu aktuálnym stavom.
 */
class SyncMailingContacts extends Command
{
    protected $signature = 'account:sync-mailing-contacts {--dry-run : len vypíše počty, nič neposiela}';

    protected $description = 'Pošle používateľov, účastníkov a kontakty kanálov Eventu do adresára Emailingu v Accounte.';

    public function handle(AccountClient $account, AccountMailing $mailing): int
    {
        if (! $account->mailingEnabled()) {
            $this->warn('Emailing v Accounte je vypnutý (ACCOUNT_MAILING / ACCOUNT_TOKEN) – nič sa neposiela.');

            return self::SUCCESS;
        }

        $emails = User::query()->whereNotNull('email')->pluck('email')
            ->merge(Ticket::query()->whereNotNull('holder_email')->where('status', '!=', TicketStatus::Cancelled->value)->distinct()->pluck('holder_email'))
            ->merge(CanalOutreach::query()->distinct()->pluck('email'))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        $sent = 0;
        $tags = [];

        foreach ($emails->chunk(500) as $chunk) {
            $contacts = $chunk->map(fn ($email) => $mailing->contact($email))->filter()->values()->all();

            foreach ($contacts as $contact) {
                foreach ($contact['tags'] as $tag) {
                    $tags[$tag] = ($tags[$tag] ?? 0) + 1;
                }
            }

            if (! $this->option('dry-run') && $contacts) {
                $account->upsertMailingContacts($contacts);
            }

            $sent += count($contacts);
        }

        $this->info(($this->option('dry-run') ? 'Poslalo by sa' : 'Poslaných').' kontaktov: '.$sent);
        foreach ($tags as $tag => $count) {
            $this->line("  {$tag}: {$count}");
        }

        return self::SUCCESS;
    }
}
