<?php

namespace App\Services\Account;

use RuntimeException;

/**
 * Account správu neprijal z dôvodu, ktorý opakovanie nevyrieši:
 *
 *   suppressed  adresa je pre Event blokovaná (trvalý bounce, odhlásenie)
 *   no_mailbox  Event nemá v Accounte nastavenú schránku
 */
class AccountMessageRejected extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
