<?php

namespace App\Enums;

/**
 * Čo vieme o jednej e-mailovej adrese kanála (viď CanalEmails).
 *
 *   unverified ──odkaz / odpoveď──▶ verified
 *        │                              │
 *        └────────── bounce ────────────┴──▶ undeliverable ──odpoveď / ručne──▶ späť
 */
enum CanalEmailStatus: string
{
    /** Adresu máme, ale schránka sa ešte neozvala. */
    case Unverified = 'unverified';

    /** Schránka potvrdila odkaz alebo nám z nej prišla odpoveď. */
    case Verified = 'verified';

    /** E-mail sa vrátil (neznámy adresát, plná schránka…). Neposiela sa na ňu. */
    case Undeliverable = 'undeliverable';
}
