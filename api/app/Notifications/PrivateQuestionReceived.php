<?php

namespace App\Notifications;

use App\Enums\QuestionBoardPhase;
use App\Models\Event;
use App\Models\Question;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Niekto poslal organizátorovi súkromnú otázku alebo podnet z publika.
 *
 * Jediný e-mail, ktorý sa z nástenky otázok posiela organizátorovi. Verejné
 * otázky sa mu neoznamujú vôbec (počas prednášky by ich prišli desiatky a vidí
 * ich na plátne), súkromný vstup sa mu ale inak nemá ako pripomenúť: na
 * verejnej stránke nie je a pisateľovi sme sľúbili odpoveď.
 *
 * Ďalšie podnety z tej istej nástenky sú pol hodiny potichu — viď
 * App\Services\Questions\PrivateQuestionAlert.
 *
 * Text otázky je v e-maile zámerne: podnet „v sále je zima" má cenu len vtedy,
 * keď sa dá prečítať bez otvárania dashboardu. Adresa pisateľa v ňom **nie je**
 * — tá neopúšťa server ani smerom k organizátorovi (Question::$hidden)
 * a odpovedá sa na stránke, nie do schránky.
 */
class PrivateQuestionReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Question $question,
        protected Event $event,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Podnet, ktorý prišiel počas akcie, je iná vec než súkromná otázka
        // týždeň dopredu — a v predmete e-mailu je to to jediné, čo rozhoduje,
        // či ho organizátor otvorí hneď. Fáza sa počíta k času vzniku otázky,
        // nie k času doručenia: e-mail môže vo fronte chvíľu počkať.
        $live = QuestionBoardPhase::for($this->event, $this->question->created_at)->isLive();
        $key = $live ? 'feedback' : 'question';

        return (new MailMessage())
            ->subject(__('mail.private_question_received.' . $key . '.subject', ['event' => $this->event->name]))
            ->markdown('mail.private-question-received', [
                'greeting' => __('mail.common.greeting'),
                'intro' => __('mail.private_question_received.' . $key . '.intro', ['event' => $this->event->name]),
                'authorName' => $this->question->author_name,
                'body' => $this->question->body,
                'hint' => __('mail.private_question_received.hint'),
                'action' => __('mail.private_question_received.action'),
                'boardUrl' => $this->boardUrl(),
                'outro' => __('mail.private_question_received.outro'),
            ]);
    }

    /**
     * Odkaz do dashboardu na nástenku podujatia. Skladá sa tu z
     * `app.frontend_url`, rovnako ako inbox v MessageReplied — je to adresa
     * SPA, ktorú backend nesmeruje.
     */
    private function boardUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            . '/dashboard/events/' . $this->event->id . '/otazky';
    }
}
