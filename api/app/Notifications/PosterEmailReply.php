<?php

namespace App\Notifications;

use App\Notifications\Channels\AccountMailChannel;
use App\Services\Posters\PosterAnalysisReport;
use App\Services\Posters\PosterEmailIntake;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Odpoveď na pozvánku poslanú e-mailom (PosterEmailIntake): čo sme
 * pochopili, čo ešte chýba (stačí odpísať) a odkaz na koncept.
 *
 * Ide vždy cez Account do konverzácie pôvodného e-mailu — tým si ju Event
 * priradí a ďalšia odpoveď odosielateľa sa vráti ku konceptu.
 */
class PosterEmailReply extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<string, mixed>  $analysis  PosterAnalysisReport::build() */
    public function __construct(
        public readonly string $draftId,
        public readonly string $conversationId,
        public readonly string $token,
        public readonly array $analysis,
        public readonly string $subject = '',
        public readonly ?CarbonInterface $expiresAt = null,
    ) {}

    public function via(object $notifiable): array
    {
        return [AccountMailChannel::class];
    }

    public function accountRef(object $notifiable): array
    {
        return [
            'type' => PosterEmailIntake::ACCOUNT_REF,
            'id' => $this->draftId,
            'label' => Str::limit('Podujatie z e-mailu: '.($this->title() ?? $this->subject), 250, ''),
            'url' => null,
        ];
    }

    public function accountConversation(): string
    {
        return $this->conversationId;
    }

    public function accountKind(): string
    {
        return 'poster.email_reply';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fields = (array) ($this->analysis['fields'] ?? []);
        $missing = array_filter($fields, fn ($field) => ($field['required'] ?? false)
            && ($field['status'] ?? null) === PosterAnalysisReport::STATUS_MISSING);
        $found = array_filter($fields, fn ($field) => ($field['status'] ?? null) !== PosterAnalysisReport::STATUS_MISSING
            && ! ($field['preview'] ?? false) && is_string($field['value'] ?? null));

        $subject = trim($this->subject) !== ''
            ? (str_starts_with(mb_strtolower($this->subject), 're:') ? $this->subject : 'Re: '.$this->subject)
            : __('mail.poster_email.subject');

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.poster_email.intro'));

        foreach ($found as $field) {
            $mail->line('**'.$field['label'].':** '.$field['value']);
        }

        if ($missing !== []) {
            $mail->line(__('mail.poster_email.missing', [
                'fields' => implode(', ', array_map(fn ($field) => mb_strtolower((string) $field['label']), $missing)),
            ]));
            $mail->line(__('mail.poster_email.reply_hint'));
        } else {
            $mail->line(__('mail.poster_email.complete'));
        }

        $url = rtrim((string) config('app.frontend_url'), '/').'/nahrat-plagat/'.$this->draftId.'?token='.$this->token;
        $mail->action(__('mail.poster_email.action'), $url);

        if ($this->expiresAt !== null) {
            $mail->line(__('mail.poster_email.expires', ['date' => $this->expiresAt->format('d. m. Y')]));
        }

        return $mail->line(__('mail.poster_email.ignore'));
    }

    private function title(): ?string
    {
        foreach ((array) ($this->analysis['fields'] ?? []) as $field) {
            if (($field['key'] ?? null) === 'title' && is_string($field['value'] ?? null)) {
                return $field['value'];
            }
        }

        return null;
    }
}
