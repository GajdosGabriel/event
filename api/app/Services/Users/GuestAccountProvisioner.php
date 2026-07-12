<?php

namespace App\Services\Users;

use App\Models\PendingProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Založí (alebo nájde) používateľský účet pre e-mail, ktorý zadal niekto iný —
 * účastník cudzej objednávky alebo odosielateľ správy organizátorovi.
 *
 * E-mail zámerne NEoverujeme: adresu zadal niekto iný, takže nemáme dôkaz, že
 * patrí majiteľovi schránky, ani jeho súhlas s podmienkami. Účet aj osobný kanál
 * preto vzniknú, ale ako „na aktiváciu" — plne sa aktivuje (potvrdí e-mail,
 * odsúhlasí podmienky) až keď sa majiteľ sám prihlási.
 */
class GuestAccountProvisioner
{
    public function __construct(
        private PersonalCanalProvisioner $canalProvisioner,
    ) {
    }

    /**
     * @param string $registeredVia Ako účet vznikol (napr. 'ticket', 'message').
     */
    public function ensure(string $email, ?string $displayName, string $registeredVia): User
    {
        $existing = User::withTrashed()->where('email', $email)->first();

        if ($existing) {
            return $existing;
        }

        $user = User::create([
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
            'registered_via' => $registeredVia,
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
