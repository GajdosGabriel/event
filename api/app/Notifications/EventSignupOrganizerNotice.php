<?php

namespace App\Notifications;

use App\Models\CanalInvitation;
use App\Models\Event;
use App\Notifications\Channels\BellChannel;
use App\Notifications\Concerns\SendsViaAccount;
use App\Services\Tickets\EventSignup;
use App\Support\DashboardUrl;
use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Organizátorovi: na jeho akciu sa niekto prihlásil (App\Services\Tickets\EventSignup).
 *
 * Dve podoby:
 *  - bez pozvánky — adresát kanál spravuje, odkaz vedie na zoznam prihlásených,
 *  - s pozvánkou — kanál je importovaný a nikto ho nespravuje; e-mail ide na
 *    kontakt kanála a ponúka kanál prevziať. Meno ani kontakt účastníka tu
 *    zámerne neuvádzame — adresát ešte nie je overený organizátor, uvidí ich až
 *    po prevzatí. Technický e-mail bez odkazu na odhlásenie. Ide cez Account (keď je zapnutý), aby sa nedoručenie vrátilo
 *    webhookom a adresa sa vyradila (CanalEmails::recordBounce).
 *
 * Pri zázname len záujmu (`$reserved` = false) `$interestReason` hovorí prečo
 * (EventSignup::INTEREST_*), aby organizátor nemusel hádať z vymenovania.
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
        protected ?string $interestReason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return [BellChannel::class, ...$this->mailVia($notifiable)];
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
        return ['message' => $this->subject(MailText::subjectName($this->event->name)), 'link' => '/dashboard/events/'.$this->event->id.'/attendees'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = MailText::name($this->event->name) ?: __('mail.common.event_fallback');

        if ($this->invitation !== null) {
            return $this->claimMail($event);
        }

        $mail = (new MailMessage)
            ->subject($this->subject(MailText::subjectName($event)))
            ->greeting(__('mail.common.greeting'))
            ->line(__($this->reserved
                ? 'mail.event_signup_organizer.intro'
                : 'mail.event_signup_organizer.intro_interest', ['event' => $event, 'name' => $this->attendeeName, 'site' => (string) config('app.name')]))
            ->line(__($this->reserved
                ? 'mail.event_signup_organizer.reserved'
                : 'mail.event_signup_organizer.interest_'.$this->reasonKey()));

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

    private function subject(string $event): string
    {
        return __($this->reserved
            ? 'mail.event_signup_organizer.subject'
            : 'mail.event_signup_organizer.subject_interest', ['event' => $event]);
    }

    /** `full` (plno, uzávierka) alebo `unavailable` (rezervácia sa neponúka). */
    private function reasonKey(): string
    {
        return $this->interestReason === EventSignup::INTEREST_FULL ? 'full' : 'unavailable';
    }

    private function claimMail(string $event): MailMessage
    {
        $canal = $this->invitation->canal?->name ?? __('mail.canal_invitation.canal_fallback');
        $url = rtrim((string) config('app.frontend_url'), '/').'/pozvanka/'.$this->invitation->token;

        $mail = (new MailMessage)
            ->subject(__('mail.event_signup_organizer.claim_subject', ['event' => MailText::subjectName($event)]))
            ->greeting(__('mail.common.greeting'))
            ->line(__($this->reserved
                ? 'mail.event_signup_organizer.claim_signup'
                : 'mail.event_signup_organizer.claim_interest', ['event' => $event, 'site' => (string) config('app.name')]));

        if ($this->registeredCount > 0) {
            $mail->line(trans_choice('mail.event_signup_organizer.count', $this->registeredCount));
        }

        // Odkaz „Pre organizátorov" patrí do prvého kontaktu (CanalEventPublishedOffer
        // pri novom kanáli), nie do každej ďalšej ponuky.
        return $mail
            ->line(__('mail.event_signup_organizer.claim_intro', ['canal' => $canal, 'site' => (string) config('app.name')]))
            ->line(__('mail.event_signup_organizer.claim_benefits'))
            ->action(__('mail.event_signup_organizer.claim_action'), $url)
            ->line(__('mail.event_signup_organizer.claim_email_note', ['email' => $this->invitation->email]))
            ->line(__('mail.event_signup_organizer.claim_ignore'));
    }
}
