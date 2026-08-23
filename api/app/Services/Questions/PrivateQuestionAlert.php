<?php

namespace App\Services\Questions;

use App\Models\Event;
use App\Models\Question;
use App\Notifications\PrivateQuestionReceived;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Upozornenie organizátorovi na súkromný vstup.
 *
 * Pri verejných otázkach sa **zámerne neposiela nič** — počas prednášky by
 * organizátorovi prišlo štyridsať e-mailov a nič by s nimi nerobil; otázky
 * vidí na plátne a v dashboarde.
 *
 * Súkromný vstup je opačný prípad a bez upozornenia by nefungoval:
 *
 * - nikde sa nezobrazí, takže sa naň nedá naraziť pri prezeraní stránky,
 * - pisateľovi sme sľúbili odpoveď e-mailom, teda vznikol záväzok,
 * - a keď ide o podnet počas akcie („v sále je zima"), má cenu len vtedy, keď
 *   sa o ňom niekto dozvie **teraz**. Zajtra je to už len sťažnosť.
 *
 * Aby sa z toho nestala tá istá lavína, pred ktorou sa e-maily pri verejných
 * otázkach bránia, platí **jedno upozornenie na nástenku za pol hodinu**.
 * Prvý podnet („je zima") príde okamžite; ďalších dvadsať, ktoré hovoria to
 * isté, už len pribudne do dashboardu. Organizátor je v tej chvíli v sále —
 * jeden e-mail ho tam obráti k dashboardu a viac ich netreba.
 *
 * Škrtenie drží cache, nie stĺpec v databáze: je to prevádzková drobnosť
 * s hodinovou životnosťou, nie údaj, ktorý by mal prežiť nasadenie.
 */
class PrivateQuestionAlert
{
    /** Ako dlho po odoslanom upozornení sa ďalšie na tej istej nástenke nepošle. */
    private const THROTTLE_MINUTES = 30;

    public function notify(Question $question, Event $event): void
    {
        $recipient = $event->messageRecipient();

        // Bez aktívneho vlastníka (importované podujatie) nemá upozornenie kam
        // ísť. Otázka aj tak vznikla — v dashboarde ju uvidí ten, kto sa naň
        // pozrie; e-mail je nadstavba, nie podmienka.
        if ($recipient === null) {
            return;
        }

        if (! $this->claimSlot((int) $question->question_board_id)) {
            return;
        }

        $notification = new PrivateQuestionReceived($question, $event);

        Notification::route('mail', $recipient->email)->notify($notification);
    }

    /**
     * Zaberie okno pre túto nástenku. `Cache::add()` je atomické — dva podnety
     * v tej istej sekunde neposielajú dva e-maily.
     */
    private function claimSlot(int $boardId): bool
    {
        return Cache::add(
            'question-board:' . $boardId . ':private-alert',
            true,
            now()->addMinutes(self::THROTTLE_MINUTES),
        );
    }
}
