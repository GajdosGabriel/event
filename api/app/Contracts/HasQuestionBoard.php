<?php

namespace App\Contracts;

use App\Models\Event;

/**
 * Model, na ktorom sa dá zapnúť nástenka otázok z publika.
 *
 * Implementuje ho Event (otázky na celé podujatie) aj TicketType s
 * `kind = workshop` (otázky na jeden blok programu). Zvyšok — vzťah, token,
 * predvolené okno — rieši InteractsAsQuestionBoard.
 */
interface HasQuestionBoard
{
    /**
     * Podujatie, ku ktorému nástenka patrí. Určuje verejnú viditeľnosť
     * (koncept nemá mať verejnú nástenku), práva v dashboarde (cez kanál
     * podujatia) aj predvolené okno otvorenia.
     */
    public function questionBoardEvent(): ?Event;

    /** Nadpis nástenky — názov workshopu, inak názov podujatia. */
    public function questionBoardTitle(): string;
}
