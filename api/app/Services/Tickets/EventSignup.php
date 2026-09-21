<?php

namespace App\Services\Tickets;

use App\Enums\AdmissionStatus;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Enums\TicketStatus;
use App\Enums\TicketTypeKind;
use App\Models\Admission;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\EventInterestRecorded;
use App\Notifications\EventReservationInvite;
use App\Notifications\EventSignupAdminNotice;
use App\Notifications\EventSignupOrganizerNotice;
use App\Notifications\TicketIssued;
use App\Repositories\Contracts\TicketRepository;
use App\Services\Canals\CanalInviter;
use App\Support\EventTimeframe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Rezervácia miesta na podujatí a všetko, čo ju sprevádza.
 *
 * Tok „Rezervovať" (portál aj hlascirkvi.sk):
 *  1. Hosť sa musí zaregistrovať a overiť e-mail. Registrácia si pamätá
 *     podujatie; po overení mu príde e-mail „môžete si rezervovať miesto"
 *     (inviteAfterVerification) s odkazom na /prihlasenie/{id}.
 *  2. Prihlásený rezervuje jedným klikom (signup): dostane bezplatnú
 *     vstupenku s QR kódom — aj pri platenej akcii, vstupné sa rieši na
 *     mieste. Len keď organizátor rezerváciu vypol alebo nastavil výhradne
 *     platené lístky (či je plno), zaznamená sa iba záujem.
 *  3. Pri každej rezervácii (aj cez bežný formulár, viď notifyAboutOrder)
 *     dostane e-mail organizátor — vlastník kanála priamo, importovaný kanál
 *     na kontakt z podujatia s pozvánkou kanál prevziať — a super-admini.
 *     Cieľom je dostať organizátora na portál.
 */
class EventSignup
{
    public const RESERVED = 'reserved';

    public const ALREADY_REGISTERED = 'already_registered';

    public const INTEREST = 'interest';

    public const CLOSED = 'closed';

    public function __construct(
        private TicketRepository $tickets,
        private AttendeeRegistrar $attendees,
        private CanalInviter $inviter,
        private DefaultReservation $defaultReservation,
    ) {}

    /** Verejne viditeľné podujatie, na ktoré sa dá hlásiť, inak null. */
    public function findOpenEvent(int|string|null $id): ?Event
    {
        if ($id === null || $id === '') {
            return null;
        }

        $event = Event::query()
            ->whereIn('status', ModelStatus::publiclyReadableValues())
            ->find($id);

        return $event && $this->isOpen($event) ? $event : null;
    }

    /** Po overení e-mailu: „účet je hotový, rezervujte si miesto". */
    public function inviteAfterVerification(Event $event, User $user): void
    {
        $this->safely(fn () => Notification::route('mail', $user->email)
            ->notify(new EventReservationInvite($event)));
    }

    /**
     * Rezervácia jedným klikom pre prihláseného používateľa.
     *
     * @return array{status: string, ticket: ?Ticket, event: Event}
     */
    public function signup(Event $event, User $user): array
    {
        if ($this->hasMainSeat($event, $user)) {
            return ['status' => self::ALREADY_REGISTERED, 'ticket' => null, 'event' => $event];
        }

        if (! $this->isOpen($event)) {
            return ['status' => self::CLOSED, 'ticket' => null, 'event' => $event];
        }

        $ticket = $this->reserve($event, $user);
        $status = $ticket ? self::RESERVED : self::INTEREST;

        if (! $ticket) {
            $this->safely(fn () => Notification::route('mail', $user->email)
                ->notify(new EventInterestRecorded($event)));
        }

        $this->notifyOrganizerAndAdmins($event, $user->displayName(), $user->email, $status);

        return ['status' => $status, 'ticket' => $ticket, 'event' => $event];
    }

    /** Objednávka cez bežný formulár lístkov — organizátor a admini sa to dozvedia tiež. */
    public function notifyAboutOrder(Ticket $ticket): void
    {
        $event = $ticket->event()->first();

        if (! $event) {
            return;
        }

        // Objednávka je už uložená — chyba pri upozornení nesmie vrátiť 500.
        try {
            $this->notifyOrganizerAndAdmins($event, (string) $ticket->holder_name, $ticket->holder_email, self::RESERVED);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function isOpen(Event $event): bool
    {
        if (EventTimeframe::hasEnded($event)) {
            return false;
        }

        return $event->registration_deadline_at === null || $event->registration_deadline_at->isFuture();
    }

    private function hasMainSeat(Event $event, User $user): bool
    {
        return Admission::query()
            ->where('event_id', $event->id)
            ->where('status', AdmissionStatus::Valid->value)
            ->whereHas('ticket', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('status', '!=', TicketStatus::Cancelled->value))
            ->whereHas('ticketType', fn ($q) => $q->where('kind', '!=', TicketTypeKind::Workshop->value))
            ->exists();
    }

    /** Vydá vstupenku zdarma, alebo null, keď sa dá zaznamenať len záujem. */
    private function reserve(Event $event, User $user): ?Ticket
    {
        $type = $this->freeTicketType($event);

        if (! $type) {
            return null;
        }

        try {
            $ticket = $this->tickets->issueForEvent($event, [
                'user_id' => $user->id,
                'holder_name' => $user->displayName(),
                'holder_email' => $user->email,
                'items' => [['ticket_type_id' => $type->id, 'quantity' => 1]],
            ]);
        } catch (HttpExceptionInterface $e) {
            // Plná kapacita, uzávierka typu… — záujem aspoň odovzdáme organizátorovi.
            Log::info('EventSignup: reservation refused, recording interest only.', [
                'event_id' => $event->id,
                'reason' => $e->getMessage(),
            ]);

            return null;
        }

        $this->attendees->registerAndNotify($ticket);

        $this->safely(fn () => Notification::route('mail', $ticket->holder_email)
            ->notify(new TicketIssued($ticket->fresh(['event', 'admissions.ticketType']))));

        return $ticket;
    }

    /**
     * Bezplatný hlavný typ lístka. Podujatie bez akýchkoľvek lístkov ho dostane
     * (DefaultReservation); kto rezerváciu vypol, tomu ju nezapíname.
     */
    private function freeTicketType(Event $event): ?TicketType
    {
        $free = $event->ticketTypes()
            ->where('is_active', true)
            ->where('kind', '!=', TicketTypeKind::Workshop->value)
            ->where(fn ($q) => $q->whereNull('price_amount')->orWhere('price_amount', '<=', 0))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        return $free ?? $this->defaultReservation->resolve($event);
    }

    private function notifyOrganizerAndAdmins(Event $event, string $name, ?string $email, string $status): void
    {
        $organizer = $this->notifyOrganizer($event, $name, $email, $status === self::RESERVED);

        $admins = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))
            ->whereNotNull('email')
            ->get();

        if ($admins->isNotEmpty()) {
            $this->safely(fn () => Notification::send(
                $admins,
                new EventSignupAdminNotice($event, $name, $email, $status, $organizer['channel'], $organizer['email']),
            ));
        }
    }

    /**
     * Upovedomí organizátora. Vráti opis, komu správa odišla — pre e-mail
     * super-adminom: `owner`, `invitation`, alebo `none` (niet koho osloviť).
     *
     * @return array{channel: 'owner'|'invitation'|'none', email: ?string}
     */
    private function notifyOrganizer(Event $event, string $name, ?string $email, bool $reserved): array
    {
        $canal = $event->canal()->first();
        $count = $this->registeredCount($event);

        $notice = fn ($invitation = null) => new EventSignupOrganizerNotice($event, $name, $email, $reserved, $count, $invitation);

        $owners = $this->managingOwners($canal);

        if ($owners->isNotEmpty()) {
            $this->safely(fn () => Notification::send($owners, $notice()));

            return ['channel' => 'owner', 'email' => $owners->pluck('email')->implode(', ')];
        }

        $contact = $this->contactEmail($event, $canal);

        if (! $canal || $contact === null) {
            return ['channel' => 'none', 'email' => null];
        }

        // Kontakt už v tíme kanála je (pozvánku prijal) → píšeme mu ako členovi.
        $member = $canal->users()->whereRaw('LOWER(users.email) = ?', [$contact])->first();

        if ($member) {
            $this->safely(fn () => $member->notify($notice()));

            return ['channel' => 'owner', 'email' => $contact];
        }

        $invitation = $this->inviter->ensureOwnerInvitation($canal, $contact);

        $this->safely(fn () => Notification::route('mail', $contact)->notify($notice($invitation)));

        return ['channel' => 'invitation', 'email' => $contact];
    }

    /**
     * Vlastníci kanála, za ktorým reálne niekto stojí. Importovaný kanál má
     * nanajvýš technického vlastníka (importéra) — toho neoslovujeme.
     *
     * @return Collection<int, User>
     */
    private function managingOwners(?Canal $canal): Collection
    {
        if (! $canal || ! in_array($canal->registration_source, [RegistrationSource::SELF, RegistrationSource::ADMIN], true)) {
            return collect();
        }

        return $canal->owners()->whereNotNull('email')->get();
    }

    private function contactEmail(Event $event, ?Canal $canal): ?string
    {
        foreach ([$event->email, $canal?->email] as $candidate) {
            $candidate = mb_strtolower(trim((string) $candidate));

            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Počet platných hlavných vstupeniek — „prihlásených je už N ľudí". */
    private function registeredCount(Event $event): int
    {
        return Admission::query()
            ->where('event_id', $event->id)
            ->where('status', AdmissionStatus::Valid->value)
            ->whereHas('ticketType', fn ($q) => $q->where('kind', '!=', TicketTypeKind::Workshop->value))
            ->count();
    }

    /**
     * Zlyhanie e-mailu nesmie zhodiť rezerváciu — vstupenka už existuje
     * a človek by pri opakovaní dostal hlášku „už ste prihlásený".
     */
    private function safely(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            Log::warning('EventSignup: notification failed.', ['error' => $e->getMessage()]);
        }
    }
}
