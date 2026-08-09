<?php

namespace App\Models\Traits;

use App\Models\ContactEmailVerification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Model má kontaktný e-mail, ktorý sa dá overiť odkazom z e-mailu.
 *
 * Trait rieši len stav a jeho integritu; odosielanie a potvrdzovanie má na
 * starosti App\Services\Contacts\ContactEmailVerifier. Rozdelenie je zámerné:
 * e-mail musí odísť len tam, kde adresu zadal človek do formulára, kým stav
 * musí sedieť vždy — aj pri importe, aj pri zmene z konzoly.
 *
 * Kľúčové pravidlo: **zmena adresy ruší overenie**. Bez toho by stačilo overiť
 * jednu adresu a potom ju prepísať na cudziu, ktorá by sa tvárila ako overená.
 *
 * Model musí mať stĺpce `email` a `email_verified_at` a byť zapísaný
 * v ContactEmailVerification::TARGETS.
 */
trait HasVerifiableEmail
{
    public static function bootHasVerifiableEmail(): void
    {
        // Len pri úprave. Pri zakladaní záznamu je adresa nová a nepotvrdená
        // sama od seba, takže tu netreba nič nulovať — a keby áno, prepísalo by
        // to aj zámerne overený stav (seedy, migrácie, testy).
        static::updating(function (Model $model): void {
            if ($model->isDirty('email')) {
                $model->email_verified_at = null;
            }
        });

        static::saved(function (Model $model): void {
            // Rozpracované overenie starej adresy je po zmene bezpredmetné.
            // Odkaz sa síce porovnáva aj pri potvrdení, no nechať ho ležať
            // by znamenalo držať v databáze adresu, ktorú už nikto nepoužíva.
            if ($model->wasChanged('email')) {
                $model->emailVerifications()->delete();
            }
        });

        static::deleted(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->emailVerifications()->delete();
        });
    }

    public function initializeHasVerifiableEmail(): void
    {
        $this->casts['email_verified_at'] = 'datetime';
    }

    /** Rozpracované overenia — bežne najviac jedno. */
    public function emailVerifications(): MorphMany
    {
        return $this->morphMany(ContactEmailVerification::class, 'verifiable');
    }

    public function hasEmail(): bool
    {
        return filled($this->email);
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->hasEmail() && $this->email_verified_at !== null;
    }

    /** Čaká adresa na potvrdenie? (odkaz odišiel a ešte platí) */
    public function pendingEmailVerification(): ?ContactEmailVerification
    {
        if (! $this->hasEmail() || $this->hasVerifiedEmail()) {
            return null;
        }

        return $this->emailVerifications()
            ->where('email', $this->email)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('sent_at')
            ->first();
    }

    public function markEmailAsVerified(): void
    {
        $this->forceFill(['email_verified_at' => now()])->save();
    }
}
