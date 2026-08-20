<?php

namespace App\Models\Traits;

use App\Models\QuestionBoard;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Spoločné pre modely s nástenkou otázok (Event, workshop = TicketType).
 * Model dopĺňa už len questionBoardEvent() a questionBoardTitle() z kontraktu
 * HasQuestionBoard.
 */
trait InteractsAsQuestionBoard
{
    public function questionBoard(): MorphOne
    {
        return $this->morphOne(QuestionBoard::class, 'boardable');
    }

    /**
     * Nástenka sa zakladá lenivo — až keď o ňu organizátor prvý raz požiada.
     * Inak by pri každom podujatí a každom workshope v databáze ležal riadok
     * s tokenom, ktorý nikto nikdy nepoužije (a importovaných podujatí sú
     * tisíce).
     *
     * Zakladá sa rovno otvorená (`is_open` má default true): kto ju práve
     * zapol, ju chce mať zapnutú. Zavrie ju jedným klikom v nastaveniach.
     */
    public function ensureQuestionBoard(): QuestionBoard
    {
        $board = $this->questionBoard()->first();

        if ($board !== null) {
            return $board;
        }

        return $this->questionBoard()->create([
            'token' => QuestionBoard::freshToken(),
        ]);
    }
}
