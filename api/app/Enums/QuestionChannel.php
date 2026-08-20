<?php

namespace App\Enums;

/**
 * Ktorou cestou otázka prichádza k nástenke.
 *
 * Nástenka je jedna, ale vedú k nej dva vchody a líšia sa v jedinej veci —
 * či otázka nesie kontakt na pisateľa:
 *
 * - **Wall** je adresa z QR premietnutého v sále. Odpoveď tam zaznie nahlas
 *   a pisateľ sedí v miestnosti, takže e-mail by bol len trenie navyše pri
 *   formulári, ktorý má byť vyplnený do troch sekúnd od naskenovania.
 * - **EventPage** je verejný detail podujatia. Tam sa človek pýta z gauča
 *   týždeň dopredu a odpoveď by musel chodiť hľadať späť na stránku — preto
 *   si ju môže vypýtať e-mailom.
 *
 * Kedy je nástenka otvorená, sa podľa vchodu **nelíši** — to rieši jediný
 * vypínač `is_open` (QuestionBoard::acceptsQuestions).
 */
enum QuestionChannel
{
    /** Nástenka z QR kódu a plátno v sále (/q/{token}). */
    case Wall;

    /** Verejný detail podujatia (/podujatia/{slug}). */
    case EventPage;

    /** Smie otázka z tohto vchodu niesť e-mail a väzbu na účet? */
    public function carriesContact(): bool
    {
        return $this === self::EventPage;
    }
}
