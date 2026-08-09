<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Contacts\ContactEmailVerifier;
use Illuminate\Database\Eloquent\Model;

/**
 * Doplní overenie kontaktného e-mailu po uložení formulára.
 *
 * Volá sa zo store/update controllerov (dashboard aj admin) — teda presne tam,
 * kde adresu zadal človek. Import a iné strojové zápisy sem nechodia zámerne,
 * viď App\Services\Contacts\ContactEmailVerifier.
 */
trait VerifiesContactEmail
{
    protected function syncContactEmailVerification(?Model $model): void
    {
        if ($model === null) {
            return;
        }

        app(ContactEmailVerifier::class)->sync($model);
    }
}
