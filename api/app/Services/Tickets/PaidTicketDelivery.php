<?php

namespace App\Services\Tickets;

use App\Enums\AdmissionStatus;
use App\Enums\AttendeeConfirmationStatus;
use App\Models\Admission;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\AttendeeTicketIssued;
use App\Notifications\TicketIssued;
use Illuminate\Support\Facades\Notification;

/**
 * Vstupenky platenej objednávky po úhrade. Kým objednávka čaká na platbu,
 * objednávateľ dostal len výzvu na úhradu a účastníci po potvrdení účasti
 * nič — QR kódy sa rozošlú až tu, keď organizátor platbu označí.
 */
class PaidTicketDelivery
{
    public function deliver(Ticket $ticket): void
    {
        $ticket->loadMissing(['event', 'admissions.ticketType']);

        if ($ticket->holder_email) {
            Notification::route('mail', $ticket->holder_email)->notify(new TicketIssued($ticket));
        }

        $holderEmail = mb_strtolower((string) $ticket->holder_email);

        // Účastníci, ktorí účasť už potvrdili, dostanú svoju vstupenku. Tí, čo
        // ešte nepotvrdili, ju dostanú až pri potvrdení (AttendeeConfirmation).
        $ticket->admissions
            ->filter(fn (Admission $a) => $a->status === AdmissionStatus::Valid
                && $a->confirmation_status === AttendeeConfirmationStatus::Confirmed
                && $a->attendee_email
                && mb_strtolower($a->attendee_email) !== $holderEmail)
            ->groupBy(fn (Admission $a) => mb_strtolower($a->attendee_email))
            ->each(function ($admissions, string $email) use ($ticket) {
                $user = User::where('email', $email)->first();

                Notification::route('mail', $email)->notify(new AttendeeTicketIssued(
                    $ticket,
                    $admissions->pluck('id')->all(),
                    $user !== null && $user->email_verified_at === null,
                ));
            });
    }
}
