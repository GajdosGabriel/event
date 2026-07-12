<?php

namespace App\Models\Traits;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Spoločné pre modely, ktorým sa dá poslať správa (Messageable). Model musí
 * doplniť už len messageRecipient() — kto je vlastník/príjemca. Zvyšok
 * (vzťah na správy, príznak kontaktovateľnosti) rieši táto trait.
 */
trait InteractsAsMessageable
{
    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'messageable');
    }

    /**
     * Dá sa tomuto cieľu poslať správa z pohľadu daného návštevníka?
     * Cieľ musí mať aktívneho príjemcu (rieši messageRecipient) a návštevník
     * nesmie byť ním samým — vlastník si neposiela správy sám sebe.
     */
    public function isContactableBy(?User $viewer): bool
    {
        $recipient = $this->messageRecipient();

        return $recipient !== null
            && ($viewer === null || $recipient->isNot($viewer));
    }
}
