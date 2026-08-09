<?php

namespace App\Notifications;

use App\Models\AttributeCheck;
use App\Support\DashboardUrl;
use App\Support\PublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * „S jedným z vašich údajov je problém" — spoločné upozornenie pre všetko, čo
 * o sebe vieme strojovo zistiť, že prestalo fungovať.
 *
 * Zámerne jedna notifikácia pre všetky atribúty, nie „WebsiteBroken",
 * „PhoneUnreachable", „IcoInvalid"…: text sa mení len v dvoch miestach —
 * o ktorý údaj ide a čo mu je. Obe sú v prekladoch
 * (`mail.attribute_issue.attributes.*` a `.reasons.*`), takže ďalší overovaný
 * údaj je otázka sondy a dvoch riadkov v lang súbore, nie novej triedy.
 *
 * Chodí majiteľovi záznamu (kto to je, rieši HasCheckedAttributes), nie na
 * kontaktnú adresu záznamu — opraviť to musí ten, kto má prístup do formulára.
 */
class AttributeIssueNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected AttributeCheck $check,
        protected Model $subject,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $alias = AttributeCheck::aliasFor($this->subject) ?? 'event';
        $type = __('mail.attribute_issue.types.'.$alias);
        $attribute = __('mail.attribute_issue.attributes.'.$this->check->attribute);
        $name = trim((string) ($this->subject->name ?? $this->subject->title ?? ''));

        $mail = (new MailMessage())
            ->subject(__('mail.attribute_issue.subject', ['attribute' => $attribute]))
            ->greeting(__('mail.common.greeting'))
            ->line($name !== ''
                ? __('mail.attribute_issue.intro_named', ['attribute' => $attribute, 'type' => $type, 'name' => $name])
                : __('mail.attribute_issue.intro', ['attribute' => $attribute, 'type' => $type]))
            ->line('**'.$this->check->value.'**')
            ->line(__('mail.attribute_issue.reasons.'.($this->check->reason ?? 'unreachable'), [
                'status' => (string) $this->check->http_status,
            ]));

        // Kde na to návštevník narazil. Bez toho by majiteľ vedel „niečo máš
        // pokazené", ale nie kde to na portáli visí — a práve to je otázka,
        // ktorú si položí ako prvú.
        if ($this->whereItHappened() !== null) {
            $mail->line(__('mail.attribute_issue.seen_on', ['url' => $this->whereItHappened()]));
        }

        $editUrl = DashboardUrl::edit($this->subject);

        if ($editUrl !== null) {
            $mail->action(__('mail.attribute_issue.action'), $editUrl);
        }

        return $mail
            ->line(__('mail.attribute_issue.recheck'))
            ->line(__('mail.attribute_issue.false_alarm'));
    }

    /**
     * Adresa našej stránky, na ktorej sa na odkaz kliklo.
     *
     * Berie sa z evidencie, kam ju zapísal beacon z verejnej stránky — a je to
     * vždy len cesta, nikdy celá adresa (viď BrokenLinkReportController).
     * Skladá sa až tu, aby sa do e-mailu nedal prepašovať odkaz na cudziu
     * doménu cez podvrhnutý parameter.
     */
    private function whereItHappened(): ?string
    {
        $path = $this->check->reported_from;

        return filled($path) ? PublicUrl::absolute((string) $path) : null;
    }
}
