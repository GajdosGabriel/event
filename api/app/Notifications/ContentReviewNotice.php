<?php

namespace App\Notifications;

use App\Models\ContentReview;
use App\Support\DashboardUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * „Pri kontrole textu sme si všimli pár vecí" — po zverejnení záznamu.
 *
 * Tón je zámerne ponuka, nie výčitka. Text je vonku, funguje a nikto ho
 * nestiahol; jediné, čo tento e-mail robí, je že ukáže na konkrétne miesta
 * a **pripomenie, že vo formulári čaká AI, ktorá ich vie opraviť**.
 *
 * Preto odkaz nevedie na detail záznamu, ale do formulára s parametrom
 * `?ai=grammar,expand` — panel sa otvorí rozbalený a s už zaškrtnutými
 * režimami, ktoré nájdené výhrady riešia. Bez toho by človek prišiel na
 * stránku plnú polí a hádal, čím začať.
 *
 * Jedna notifikácia pre podujatie, miesto aj kanál: mení sa len slovo v prvej
 * vete a to je v prekladoch (`mail.content_review.types.*`).
 */
class ContentReviewNotice extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{severity: string, mode: string, message: string, quote: string}>  $issues
     */
    public function __construct(
        protected ContentReview $review,
        protected Model $subject,
        protected array $issues,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $alias = app(\App\Services\Publishing\PublishReadiness::class)->aliasFor($this->subject) ?? 'event';
        $type = __('mail.content_review.types.'.$alias);
        $name = trim((string) ($this->subject->name ?? ''));

        $mail = (new MailMessage())
            ->subject(__('mail.content_review.subject', ['name' => $name !== '' ? $name : $type]))
            ->greeting(__('mail.common.greeting'))
            ->line($name !== ''
                ? __('mail.content_review.intro_named', ['type' => $type, 'name' => $name])
                : __('mail.content_review.intro', ['type' => $type]));

        // Najviac tri. E-mail nie je zoznam úloh — má presvedčiť, že sa oplatí
        // kliknúť; zvyšok je aj tak vidieť vo formulári nad popisom.
        foreach (array_slice($this->issues, 0, 3) as $issue) {
            $quote = trim((string) ($issue['quote'] ?? ''));

            $mail->line('• '.$issue['message'].($quote !== '' ? ' — *„'.$quote.'"*' : ''));
        }

        $url = $this->editUrl();

        if ($url !== null) {
            $mail->action(__('mail.content_review.action'), $url);
        }

        return $mail
            ->line(__('mail.content_review.assistant'))
            ->line(__('mail.content_review.no_change'));
    }

    /**
     * Formulár s otvoreným AI panelom.
     *
     * Režimy sa skladajú z nájdených výhrad, nie z parametra zvonku — do
     * adresy sa tak nedá prepašovať nič iné (hodnoty pochádzajú z pevného
     * zoznamu PromptContentReview::MODES).
     */
    private function editUrl(): ?string
    {
        $base = DashboardUrl::edit($this->subject);

        if ($base === null) {
            return null;
        }

        $modes = $this->review->suggestedModes();

        return $modes === [] ? $base : $base.'?ai='.implode(',', $modes);
    }
}
