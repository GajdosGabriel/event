<?php

namespace App\Notifications;

use App\Models\CanalInvitation;
use App\Models\Event;
use App\Notifications\Concerns\SendsViaAccount;
use App\Support\EventDateRange;
use App\Support\PublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

/**
 * Kontaktu nespravovaného kanála: zverejnili sme jeho akciu, kanál si môže
 * prevziať (App\Services\Canals\CanalEventOffer). Pri prvej akcii nového
 * kanála e-mail hovorí aj o vytvorení kanála.
 *
 * Technický e-mail, nie kampaň — ide cez Account messaging (keď je zapnutý),
 * aby sa nedoručenie vrátilo webhookom a adresa sa vyradila.
 */
class CanalEventPublishedOffer extends Notification implements ShouldQueue
{
    use Queueable, SendsViaAccount;

    public function __construct(
        protected Event $event,
        protected CanalInvitation $invitation,
        protected bool $newCanal = false,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->mailVia($notifiable);
    }

    public function accountRef(object $notifiable): array
    {
        return $this->canalRef($this->event->loadMissing('canal')->canal);
    }

    public function accountKind(): string
    {
        return 'canal.event_offer';
    }

    public function isNewCanal(): bool
    {
        return $this->newCanal;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->event->loadMissing(['canal', 'venue']);

        $event = $this->event->name ?: __('mail.common.event_fallback');
        $canal = $this->event->canal?->name ?? __('mail.canal_invitation.canal_fallback');
        $url = rtrim((string) config('app.frontend_url'), '/').'/pozvanka/'.$this->invitation->token;
        $unsubscribe = URL::signedRoute('public.email.unsubscribe', ['email' => $this->invitation->email]);
        $when = EventDateRange::label($this->event->start_at, $this->event->end_at);
        $variant = $this->newCanal ? 'new_canal' : 'new_event';
        $replace = ['event' => $event, 'canal' => $canal, 'site' => (string) config('app.name')];

        $mail = (new MailMessage)
            ->subject(__('mail.canal_event_offer.'.$variant.'.subject', $replace))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.canal_event_offer.'.$variant.'.intro', $replace));

        if ($when) {
            $mail->line(__('mail.canal_event_offer.when', ['when' => $when]));
        }

        if ($this->event->venue?->name) {
            $mail->line(__('mail.canal_event_offer.where', ['where' => $this->event->venue->name]));
        }

        return $mail
            ->line(__('mail.canal_event_offer.event_link', ['url' => PublicUrl::event($this->event)]))
            ->line(__('mail.canal_event_offer.contact_note'))
            ->line(__('mail.event_signup_organizer.claim_benefits'))
            ->action(__('mail.canal_event_offer.action'), $url)
            ->line(__('mail.common.for_organizers', ['url' => PublicUrl::forOrganizers()]))
            ->line(__('mail.canal_invitation.email_note', ['email' => $this->invitation->email]))
            ->line(__('mail.event_signup_organizer.claim_ignore'))
            ->line(__('mail.event_signup_organizer.claim_unsubscribe', ['url' => $unsubscribe]))
            ->withSymfonyMessage(function (Email $message) use ($unsubscribe) {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>');
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }
}
