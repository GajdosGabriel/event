<?php

namespace App\Policies;

use App\Models\QuestionBoard;
use App\Models\User;

/**
 * Nástenka nemá vlastného vlastníka — práva dedí od podujatia, ku ktorému
 * patrí (aj keď je zavesená na workshope). Kto smie upraviť podujatie, smie
 * nástenku zapnúť, nastaviť aj moderovať.
 *
 * Zámerne BEZ DeniesArchivedUpdate: archivované podujatie je práve to, ktoré
 * už prebehlo, a dopisovanie odpovedí k otázkam je vec, ktorú organizátor robí
 * až potom. Zámok na archivované záznamy chráni obsah katalógu, nie nástenku.
 */
class QuestionBoardPolicy
{
    public function view(User $user, QuestionBoard $board): bool
    {
        $event = $board->event();

        return $event !== null && $user->canInCanal((int) $event->canal_id, 'event.view');
    }

    /** Nastavenia, rotácia tokenu aj moderovanie otázok. */
    public function manage(User $user, QuestionBoard $board): bool
    {
        $event = $board->event();

        return $event !== null && $user->canInCanal((int) $event->canal_id, 'event.update');
    }
}
