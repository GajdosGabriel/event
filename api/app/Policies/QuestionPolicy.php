<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

/**
 * Otázka nemá autora s účtom, takže sa tu nedá o čo oprieť okrem nástenky —
 * všetko ide cez QuestionBoardPolicy::manage().
 */
class QuestionPolicy
{
    public function moderate(User $user, Question $question): bool
    {
        // Explicitný dotaz namiesto `$question->board` — mimo produkcie je
        // lenivé načítanie vzťahu tvrdá chyba (Model::preventLazyLoading).
        $board = $question->relationLoaded('board')
            ? $question->getRelation('board')
            : $question->board()->first();

        return $board !== null && $user->can('manage', $board);
    }

    public function delete(User $user, Question $question): bool
    {
        return $this->moderate($user, $question);
    }
}
