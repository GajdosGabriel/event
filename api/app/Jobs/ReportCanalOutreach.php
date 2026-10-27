<?php

namespace App\Jobs;

use App\Models\CanalOutreach;
use App\Services\Account\AccountClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Nahlási ponuku prevzatia kanála do Account Emailingu
 * (App\Services\Canals\CanalOutreachSender).
 *
 * Najprv kontakt (bez neho automatizácia typu ponuka mail nezaradí), potom
 * udalosť. Opakovanie je bezpečné: kontakt sa len aktualizuje a udalosť
 * Account deduplikuje podľa `dedupeKey`. Výsledok sa zapíše do oslovenia —
 * `not_queued` znamená, že na trigger nie je aktívna kampaň.
 */
class ReportCanalOutreach implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600, 10800];

    /**
     * @param  array<string, mixed>  $contact
     * @param  array{type: string, id: string, label?: string, url?: string}  $ref
     * @param  array<string, scalar|null>  $variables
     */
    public function __construct(
        public int $outreachId,
        public array $contact,
        public string $event,
        public array $ref,
        public array $variables,
        public string $dedupeKey,
        public ?\DateTimeInterface $occurredAt = null,
    ) {
        $this->occurredAt ??= now();
    }

    public function handle(AccountClient $account): void
    {
        $outreach = CanalOutreach::find($this->outreachId);

        // Medzičasom prevzatý alebo zmazaný kanál už nehlásime.
        if (! $outreach || $outreach->status !== CanalOutreach::REPORTED || $outreach->canal?->isManaged() || $outreach->canal?->trashed()) {
            return;
        }

        $account->upsertMailingContacts([$this->contact]);

        $queued = $account->mailingEvent(
            $this->event,
            (string) $this->contact['email'],
            $this->ref,
            array_filter($this->variables, fn ($v) => $v !== null),
            $this->dedupeKey,
            $this->occurredAt,
            $this->contact['name'] ?? null,
        );

        $outreach->forceFill([
            'status' => $queued > 0 ? CanalOutreach::QUEUED : CanalOutreach::NOT_QUEUED,
            'account_queued' => $queued,
        ])->save();
    }
}
