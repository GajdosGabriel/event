<?php

namespace App\Models\Traits;

use App\Models\Message;
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

    /** Dá sa tomuto cieľu poslať správa? (má vlastníka s e-mailom) */
    public function isContactable(): bool
    {
        return $this->messageRecipient() !== null;
    }
}
