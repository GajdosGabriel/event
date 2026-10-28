<?php

namespace App\Console\Commands;

use App\Services\Account\AccountClient;
use App\Services\Account\AccountMailing;
use Illuminate\Console\Command;

/**
 * Pošle do Accountu zoznam udalostí, na ktoré sa dá v Emailingu naviazať
 * automatizácia. Spúšťa sa pri nasadení (a po pridaní case do MailingTrigger).
 */
class RegisterMailingTriggers extends Command
{
    protected $signature = 'emailing:register-triggers';

    protected $description = 'Zaregistruje udalosti a štítky kontaktov pre Emailing v Accounte.';

    public function handle(AccountClient $account, AccountMailing $mailing): int
    {
        if (! $account->mailingEnabled()) {
            $this->warn('Emailing v Accounte je vypnutý (ACCOUNT_MAILING / ACCOUNT_TOKEN) – nič sa neposiela.');

            return self::SUCCESS;
        }

        $account->registerMailingTriggers($triggers = $mailing->triggers());
        $account->registerMailingTags($tags = $mailing->tags());

        $this->info('Zaregistrovaných udalostí: '.count($triggers).', štítkov kontaktov: '.count($tags));

        return self::SUCCESS;
    }
}
