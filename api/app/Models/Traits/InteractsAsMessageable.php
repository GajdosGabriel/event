<?php

namespace App\Models\Traits;

use App\Models\Message;
use App\Models\User;
use App\Services\Canals\CanalTakeover;
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
     * nesmie byť ním samým — vlastník si neposiela správy sám sebe. Bez príjemcu
     * stačí kontakt nespravovaného kanála (CanalRelay).
     */
    public function isContactableBy(?User $viewer): bool
    {
        $recipient = $this->messageRecipient();

        if ($recipient === null) {
            return app(CanalTakeover::class)->addressFor($this) !== null;
        }

        return $viewer === null || $recipient->isNot($viewer);
    }
}
