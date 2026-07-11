<?php

namespace App\Services\Tickets;

use App\Models\PendingProfile;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\AttendeeConfirmationRequest;
use App\Services\Users\PersonalCanalProvisioner;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Ďalší účastníci objednávky (meno + e-mail pri vstupenkách 2..n):
 * každému založí používateľský účet aj osobný kanál (aby sa neskôr mohol
 * prihlasovať na ďalšie podujatia a workshopy) a pošle e-mail so žiadosťou
 * o potvrdenie rezervácie. Vstupenku s QR dostane až po potvrdení.
 */
class AttendeeRegistrar
{
    public function __construct(
        private PersonalCanalProvisioner $canalProvisioner,
        private AttendeeConfirmation $confirmation,
    ) {
    }

    public function registerAndNotify(Ticket $ticket): void
    {
        // Označí cudzie vstupenky ako „čaká na potvrdenie" a vráti ich po e-mailoch.
        $groups = $this->confirmation->prepare($ticket);

        foreach ($groups as $email => $admissions) {
            $user = $this->ensureUser($email, $admissions->first()->attendee_name);

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

    /**
     * Založí účet aj osobný kanál pre e-mail účastníka, ak ešte neexistuje.
     *
     * E-mail zámerne NEoverujeme: adresu zadal niekto iný (objednávateľ), takže
     * nemáme dôkaz, že patrí majiteľovi schránky, ani jeho súhlas s podmienkami.
     * Účet aj kanál preto vzniknú, ale ako „na aktiváciu" — plne sa aktivuje
     * (potvrdí e-mail, odsúhlasí podmienky) až keď sa majiteľ sám prihlási.
     */
    private function ensureUser(string $email, ?string $displayName): User
    {
        $existing = User::withTrashed()->where('email', $email)->first();

        if ($existing) {
            return $existing;
        }

        $user = User::create([
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
            'registered_via' => 'ticket',
        ]);

        if ($displayName !== null && trim($displayName) !== '') {
            PendingProfile::create([
                'user_id' => $user->id,
                'display_name' => trim($displayName),
            ]);
        }

        // Kanál vytvoríme explicitne — účet ostáva neoverený, takže by ho
        // observer (viazaný na overenie e-mailu) sám nezaložil.
        $this->canalProvisioner->ensureFor($user);

        return $user;
    }
}
