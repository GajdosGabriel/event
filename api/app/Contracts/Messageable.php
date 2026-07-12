<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Čokoľvek, čomu môže návštevník poslať správu cez tlačidlo „Poslať správu"
 * (podujatie, miesto, kanál…). Určuje, kto správu dostane — vždy používateľ
 * (vlastník) s e-mailom. Ak taký nie je, cieľ nie je kontaktovateľný.
 */
interface Messageable
{
    /** Používateľ, ktorému správa príde (vlastník s e-mailom), alebo null. */
    public function messageRecipient(): ?User;
}
