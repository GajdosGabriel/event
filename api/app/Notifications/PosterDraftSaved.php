<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Odkaz späť na rozpracovaný plagát.
 *
 * Analýza beží pred registráciou, takže výsledok existuje skôr, než má komu
 * patriť. Tento e-mail je jediná vec, ktorá ho drží pri živote naprieč
 * zariadeniami a naprieč pauzou, kým si človek overí účet.
 *
 * Model sa sem zámerne nepredáva: notifikácia ide do fronty a serializoval by
 * sa s ňou aj celý `detection` JSON (desiatky kilobajtov na jeden e-mail).
 */
class PosterDraftSaved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $draftId,
        protected string $token,
        protected ?string $eventName = null,
        protected ?CarbonInterface $expiresAt = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/')
            . '/nahrat-plagat/' . $this->draftId . '?token=' . $this->token;

        $mail = (new MailMessage())
            ->subject(__('mail.poster_draft.subject'))
            ->greeting(__('mail.common.greeting'))
            ->line($this->eventName !== null && $this->eventName !== ''
                ? __('mail.poster_draft.intro_named', ['name' => $this->eventName])
                : __('mail.poster_draft.intro'))
            ->line(__('mail.poster_draft.next'))
            ->action(__('mail.poster_draft.action'), $url);

        if ($this->expiresAt !== null) {
            $mail->line(__('mail.poster_draft.expires', [
                'date' => $this->expiresAt->format('d. m. Y'),
            ]));
        }

        return $mail->line(__('mail.poster_draft.ignore'));
    }
}
