<?php

namespace App\Notifications;

use App\Models\Question;
use App\Support\PublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Organizátor odpovedal na otázku z verejného detailu podujatia.
 *
 * Jediný e-mail, ktorý sa pisateľovi otázky kedy pošle — adresu si vypýtal
 * práve na toto a v okamihu odoslania sa mu z databázy maže. Preto tu nie je
 * odkaz na odhlásenie: nie je z čoho sa odhlasovať a ďalšia správa už nepríde
 * (poistkou je `questions.answer_notified_at`).
 *
 * Otázka z QR nástenky v sále takto nikdy nepríde — tam sa adresa nezbiera,
 * lebo odpoveď zaznie nahlas a pisateľ sedí v miestnosti (QuestionChannel).
 */
class QuestionAnswered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Question $question,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Notifikácia je ShouldQueue — na workeri sa model načíta odznova a bez
        // relácií (Model::preventLazyLoading je mimo produkcie zapnuté).
        $event = $this->question->loadMissing('board')->board?->event();

        return (new MailMessage())
            ->subject(__('mail.question_answered.subject', ['event' => $event?->name ?? '']))
            ->markdown('mail.question-answered', [
                'greeting' => __('mail.common.greeting'),
                'eventName' => $event?->name,
                'eventUrl' => $event !== null ? PublicUrl::event($event) : null,
                'questionBody' => $this->question->body,
                'answerBody' => $this->question->answer_body,
            ]);
    }
}
