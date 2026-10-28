<?php

namespace App\Jobs;

use App\Services\Account\AccountClient;
use App\Services\Account\AccountMailing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pošle do adresára v Accounte aktuálny stav jednej adresy.
 *
 * Stav sa skladá až pri behu jobu z databázy (účet, kanál, registrácie,
 * oslovenie), takže opakovanie ani poradie jobov nevadí – posledný beh pošle
 * pravdu. Keď už adresa v Evente nič nemá, kontakt sa v Accounte zmaže.
 */
class SyncMailingContact implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600, 10800];

    public function __construct(public string $email) {}

    public function handle(AccountClient $account, AccountMailing $mailing): void
    {
        $contact = $mailing->contact($this->email);

        $contact
            ? $account->upsertMailingContacts([$contact])
            : $account->removeMailingContacts([$this->email]);
    }
}
