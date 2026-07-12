<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Notifications\AttendeeConfirmationRequest;
use App\Services\Users\GuestAccountProvisioner;
use Illuminate\Support\Facades\Notification;

/**
 * Ďalší účastníci objednávky (meno + e-mail pri vstupenkách 2..n):
 * každému založí používateľský účet aj osobný kanál (aby sa neskôr mohol
 * prihlasovať na ďalšie podujatia a workshopy) a pošle e-mail so žiadosťou
 * o potvrdenie rezervácie. Vstupenku s QR dostane až po potvrdení.
 */
class AttendeeRegistrar
{
    public function __construct(
        private GuestAccountProvisioner $accounts,
        private AttendeeConfirmation $confirmation,
    ) {
    }

    public function registerAndNotify(Ticket $ticket): void
    {
        // Označí cudzie vstupenky ako „čaká na potvrdenie" a vráti ich po e-mailoch.
        $groups = $this->confirmation->prepare($ticket);

        foreach ($groups as $email => $admissions) {
            $user = $this->accounts->ensure($email, $admissions->first()->attendee_name, 'ticket');

            // Účet vytvorený z cudzej objednávky ešte nie je plne aktívny — e-mail
            // zadal objednávateľ, majiteľ schránky ho ešte nepotvrdil ani neodsúhlasil
            // podmienky. Vtedy ho v e-maile pozveme na plnú aktiváciu účtu.
            $needsActivation = $user->email_verified_at === null;

            Notification::route('mail', $email)
                ->notify(new AttendeeConfirmationRequest(
                    $ticket,
                    $admissions->pluck('id')->all(),
                    (string) $admissions->first()->confirmation_token,
                    $needsActivation,
                ));
        }
    }
}
