<?php

namespace App\Notifications;

use App\Models\CanalInvitation;
use App\Models\Event;
use App\Support\DashboardUrl;
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
 *    kontakt z podujatia a ponúka kanál prevziať. Kontakt účastníka tu zámerne
 *    neuvádzame — adresát ešte nie je overený organizátor, uvidí ho až po prevzatí.
 */
class EventSignupOrganizerNotice extends Notification implements ShouldQueue
{
    use Queueable;

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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->event->name ?: __('mail.common.event_fallback');
        $name = $this->attendeeName;

        $mail = (new MailMessage)
            ->subject(__('mail.event_signup_organizer.subject', ['event' => $event]))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.event_signup_organizer.intro', ['event' => $event, 'name' => $name]))
            ->line(__($this->reserved
                ? 'mail.event_signup_organizer.reserved'
                : 'mail.event_signup_organizer.interest'));

        if ($this->registeredCount > 0) {
            $mail->line(trans_choice('mail.event_signup_organizer.count', $this->registeredCount));
        }

        if ($this->invitation === null) {
            if ($this->attendeeEmail) {
                $mail->line(__('mail.event_signup_organizer.contact', ['email' => $this->attendeeEmail]));
            }

            return $mail
                ->action(
                    __('mail.event_signup_organizer.action'),
                    DashboardUrl::base().'/events/'.$this->event->id.'/attendees',
                );
        }

        $canal = $this->invitation->canal?->name ?? __('mail.canal_invitation.canal_fallback');
        $url = rtrim((string) config('app.frontend_url'), '/').'/pozvanka/'.$this->invitation->token;

        return $mail
            ->line(__('mail.event_signup_organizer.claim_intro', ['canal' => $canal]))
            ->line(__('mail.event_signup_organizer.claim_benefits'))
            ->action(__('mail.event_signup_organizer.claim_action'), $url)
            ->line(__('mail.canal_invitation.email_note', ['email' => $this->invitation->email]))
            ->line(__('mail.event_signup_organizer.claim_ignore'));
    }
}
