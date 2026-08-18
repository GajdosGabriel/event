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
     * Predvolené okno je „dve hodiny pred začiatkom až dve hodiny po konci".
     * Dve hodiny pred preto, aby sa dalo skúšobne naskenovať ešte pri príprave
     * techniky; dve hodiny po preto, že otázka po prednáške na chodbe je stále
     * legitímna otázka. Organizátor si to v nastaveniach prepíše alebo zmaže.
     */
    public function ensureQuestionBoard(): QuestionBoard
    {
        $board = $this->questionBoard()->first();

        if ($board !== null) {
            return $board;
        }

        $event = $this->questionBoardEvent();

        return $this->questionBoard()->create([
            'token' => QuestionBoard::freshToken(),
            'opens_at' => $event?->start_at?->copy()->subHours(2),
            'closes_at' => ($event?->end_at ?? $event?->start_at)?->copy()->addHours(2),
        ]);
    }
}
