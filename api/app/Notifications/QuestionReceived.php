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
 * Na nástenke pribudla otázka — e-mail organizátorovi.
 *
 * Chodí pri **každej** otázke (viď App\Services\Questions\QuestionAlert).
 * Líši sa len text, a to podľa toho, čo organizátor v tej chvíli potrebuje
 * vedieť skôr, než e-mail otvorí:
 *
 * - **podnet z publika** — súkromný vstup počas akcie („v sále je zima").
 *   Rieši sa teraz alebo nikdy, preto vlastný predmet.
 * - **súkromná otázka** — nikde sa nezverejní, odpoveď ide len e-mailom.
 * - **verejná otázka** — visí na stránke, kým na ňu niekto neodpovie.
 *
 * Text otázky je v e-maile zámerne: podnet má cenu len vtedy, keď sa dá
 * prečítať bez otvárania dashboardu. Adresa pisateľa v ňom **nie je** — tá
 * neopúšťa server ani smerom k organizátorovi (Question::$hidden) a odpovedá
 * sa na stránke, nie do schránky.
 */
class QuestionReceived extends Notification implements ShouldQueue
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
        $key = $this->textKey();

        return (new MailMessage())
            ->subject(__('mail.question_received.' . $key . '.subject', ['event' => $this->event->name]))
            ->markdown('mail.question-received', [
                'greeting' => __('mail.common.greeting'),
                'intro' => __('mail.question_received.' . $key . '.intro', ['event' => $this->event->name]),
                'authorName' => $this->question->author_name,
                'body' => $this->question->body,
                'hint' => __('mail.question_received.' . $key . '.hint'),
                'action' => __('mail.question_received.action'),
                'boardUrl' => $this->boardUrl(),
            ]);
    }

    /**
     * Ktorá sada textov. Fáza sa počíta k času vzniku otázky, nie k času
     * doručenia — e-mail môže vo fronte chvíľu počkať a „podnet z akcie"
     * by sa inak zmenil na obyčajnú otázku.
     */
    private function textKey(): string
    {
        if (! $this->question->isPrivate()) {
            return 'public';
        }

        return QuestionBoardPhase::for($this->event, $this->question->created_at)->isLive()
            ? 'feedback'
            : 'private';
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
