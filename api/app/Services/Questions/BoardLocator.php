<?php

namespace App\Services\Questions;

use App\Enums\ModelStatus;
use App\Models\QuestionBoard;
use App\Support\BoardToken;

/**
 * Preloží token z adresy `/q/{token}` na nástenku — a rozhodne, či ju smie
 * vidieť verejnosť.
 *
 * Je to jediné miesto, kde sa toto rozhodnutie robí; verejná stránka, polling
 * aj sťahovanie snímky ho zdieľajú, aby sa nedalo stať, že jedna cesta pustí
 * ďalej to, čo druhá zamietla.
 */
class BoardLocator
{
    /**
     * Nástenka pre verejnú adresu, alebo 404.
     *
     * 404 (nie 403) je zámer: keby sme rozlišovali „token neexistuje" a „token
     * existuje, ale podujatie je koncept", dal by sa cez to zisťovať, ktoré
     * tokeny sú platné.
     */
    public function publicOrFail(?string $rawToken): QuestionBoard
    {
        $token = BoardToken::normalize($rawToken);

        if ($token === null) {
            abort(404);
        }

        $board = QuestionBoard::query()->where('token', $token)->first();

        if ($board === null) {
            abort(404);
        }

        $event = $board->event();

        // Cieľ mohol byť medzitým zmazaný (workshop), alebo podujatie ešte nie
        // je verejné. Nástenka konceptu nesmie fungovať — inak by sa dala nájsť
        // uhádnutím tokenu skôr, než organizátor podujatie zverejní.
        if ($event === null || ! in_array($event->status?->value, ModelStatus::publiclyReadableValues(), true)) {
            abort(404);
        }

        return $board;
    }
}
