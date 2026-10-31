<?php

namespace App\Notifications;

use App\Models\CanalInvitation;
use App\Models\Event;
use App\Notifications\Concerns\SendsViaAccount;
use App\Support\DashboardUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

/**
 * Organizátorovi: na jeho akciu sa niekto prihlásil (App\Services\Tickets\EventSignup).
 *
 * Dve podoby:
 *  - bez pozvánky — adresát kanál spravuje, odkaz vedie na zoznam prihlásených,
 *  - s pozvánkou — kanál je importovaný a nikto ho nespravuje; e-mail ide na
 *    kontakt kanála a ponúka kanál prevziať. Meno ani kontakt účastníka tu
 *    zámerne neuvádzame — adresát ešte nie je overený organizátor, uvidí ich až
 *    po prevzatí. Ide cez Account (keď je zapnutý), aby sa nedoručenie vrátilo
 *    webhookom a adresa sa vyradila (CanalEmails::recordBounce).
 */
class EventSignupOrganizerNotice extends Notification implements ShouldQueue
{
    use Queueable, SendsViaAccount;

    public function __construct(
        protected Event $event,
        protected string $attendeeName,
        protected ?string $attendeeEmail,
        protected bool $reserved,
        protected int $registeredCount,
        protected ?CanalInvitation $invitation = null,
    ) {}

    public function via(object $notifiable): array
    {
        return [\App\Notifications\Channels\BellChannel::class, ...$this->mailVia($notifiable)];
    }

    public function accountRef(object $notifiable): array
    {
        return $this->canalRef($this->event->loadMissing('canal')->canal);
    }

    public function accountKind(): string
    {
        return 'canal.signup_offer';
    }

    public function toBell(object $notifiable): array
    {
        return ['message' => __('mail.event_signup_organizer.subject', ['event' => $this->event->name]), 'link' => '/dashboard/events/'.$this->event->id.'/attendees'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->event->name ?: __('mail.common.event_fallback');

        if ($this->invitation !== null) {
            return $this->claimMail($event);
        }

        $name = $this->attendeeName;

        $mail = (new MailMessage)
            ->subject(__('mail.event_signup_organizer.subject', ['event' => $event]))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.event_signup_organizer.intro', ['event' => $event, 'name' => $name, 'site' => (string) config('app.name')]))
            ->line(__($this->reserved
                ? 'mail.event_signup_organizer.reserved'
                : 'mail.event_signup_organizer.interest'));

        if ($this->registeredCount > 0) {
            $mail->line(trans_choice('mail.event_signup_organizer.count', $this->registeredCount));
        }

        if ($this->attendeeEmail) {
            $mail->line(__('mail.event_signup_organizer.contact', ['email' => $this->attendeeEmail]));
        }

        return $mail
            ->action(
                __('mail.event_signup_organizer.action'),
                DashboardUrl::base().'/events/'.$this->event->id.'/attendees',
            );
    }

    private function claimMail(string $event): MailMessage
    {
        $canal = $this->invitation->canal?->name ?? __('mail.canal_invitation.canal_fallback');
        $url = rtrim((string) config('app.frontend_url'), '/').'/pozvanka/'.$this->invitation->token;
        $unsubscribe = URL::signedRoute('public.email.unsubscribe', ['email' => $this->invitation->email]);

        $mail = (new MailMessage)
            ->subject(__('mail.event_signup_organizer.claim_subject', ['event' => $event]))
            ->greeting(__('mail.common.greeting'))
            ->line(__($this->reserved
                ? 'mail.event_signup_organizer.claim_signup'
                : 'mail.event_signup_organizer.claim_interest', ['event' => $event, 'site' => (string) config('app.name')]));

        if ($this->registeredCount > 0) {
            $mail->line(trans_choice('mail.event_signup_organizer.count', $this->registeredCount));
        }

        return $mail
            ->line(__('mail.event_signup_organizer.claim_intro', ['canal' => $canal]))
            ->line(__('mail.event_signup_organizer.claim_benefits'))
            ->action(__('mail.event_signup_organizer.claim_action'), $url)
            ->line(__('mail.canal_invitation.email_note', ['email' => $this->invitation->email]))
            ->line(__('mail.event_signup_organizer.claim_ignore'))
            ->line(__('mail.event_signup_organizer.claim_unsubscribe', ['url' => $unsubscribe]))
            ->withSymfonyMessage(function (Email $message) use ($unsubscribe) {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>');
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }
}
