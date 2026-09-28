<?php

namespace App\Services\Questions;

use App\Enums\CanalNotificationTopic;
use App\Models\Event;
use App\Models\Question;
use App\Notifications\QuestionReceived;
use App\Services\Canals\CanalRecipients;
use Illuminate\Support\Facades\Notification;

/**
 * Nová otázka → e-mail organizátorovi. Bez podmienok.
 *
 * Pravidlo je zámerne jedno a bez výnimiek: **každá otázka sa niekomu oznámi.**
 * Otázka, o ktorej sa organizátor nedozvie, je otázka bez odpovede — a to platí
 * rovnako pre verejnú („je tam parkovanie?", visí na stránke nezodpovedaná),
 * pre súkromnú (tú nevidno vôbec nikde) aj pre podnet z akcie („v sále je
 * zima"), ktorý má cenu len teraz.
 *
 * Skoršie verzie tu mali obmedzovanie počtu e-mailov podľa fázy podujatia.
 * Bola to schovaná zložitosť, ktorú si po mesiaci nikto nepamätá, a jej cena
 * bola vysoká: správa, ktorá nikdy nepríde, sa nedá odlíšiť od chyby. Cena
 * opačnej voľby je e-mail navyše.
 *
 * Druhá polovica dvojice je [`QuestionAnswered`](../../Notifications/QuestionAnswered.php):
 * keď organizátor odpoveď dopíše, e-mail dostane pisateľ (ak nechal adresu —
 * pri súkromnej otázke ju necháva vždy).
 */
class QuestionAlert
{
    public function __construct(
        private CanalRecipients $recipients,
    ) {}

    public function notify(Question $question, Event $event): void
    {
        $recipient = $event->questionBoardRecipient();
        $notice = new QuestionReceived($question, $event);
        $canal = $this->recipients->canalOf($event);

        // Príjemcu sa nájsť nemusí (podujatie bez použiteľného účtu). Otázka aj
        // tak vznikla — v dashboarde ju uvidí ten, kto sa naň pozrie; e-mail je
        // nadstavba, nie podmienka.
        if ($recipient !== null
            && $this->recipients->mayNotify($canal, $recipient, CanalNotificationTopic::Questions, $notice, $event)) {
            Notification::route('mail', $recipient->email)->notify($notice);
        }

        // Ďalší členovia tímu, ktorí majú otázky zapnuté.
        $this->recipients->fanOut($canal, CanalNotificationTopic::Questions, $notice, [$recipient], $event);
    }
}
