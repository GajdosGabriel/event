<?php

namespace App\Services\Contacts;

use App\Models\ContactEmailVerification;
use App\Notifications\ContactEmailVerificationRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Overovanie kontaktných e-mailov modelov (kanál, miesto, podujatie, firma).
 *
 * Jediné miesto, kde overovací e-mail vzniká a kde sa odkaz z neho uplatňuje.
 *
 * Prečo sa neposiela z model eventu (`saved`), ale volaním zo controllera:
 * adresy do databázy nepíšu len formuláre. Import ťahá kontakt z cudzích
 * stránok a rozposielať overovací e-mail komukoľvek, koho adresu robot našiel,
 * by z nás urobilo spamera. Odkaz preto odchádza len tam, kde adresu zadal
 * človek — teda z formulárov (dashboard aj admin).
 *
 * Samotné zneplatnenie overenia pri zmene adresy je naopak v modeli
 * (HasVerifiableEmail), lebo platiť musí vždy, aj pri importe.
 */
class ContactEmailVerifier
{
    /**
     * Zosúladí stav overenia so zadanou adresou — volá sa po uložení formulára.
     *
     * Nič nerobí, keď je adresa prázdna alebo už overená; inak pošle odkaz,
     * ak sa na tú istú adresu nedávno neposielal.
     */
    public function sync(Model $model): void
    {
        if (! $this->supports($model) || ! $model->hasEmail() || $model->hasVerifiedEmail()) {
            return;
        }

        $this->issue($model);
    }

    /**
     * Vydá a odošle nový overovací odkaz.
     *
     * @param  bool  $force  Preskočí čakaciu lehotu (ručné „poslať znova" ju má rešpektovať).
     * @return bool `false`, keď sa neposielalo — adresa chýba, je overená alebo ešte beží lehota.
     */
    public function issue(Model $model, bool $force = false): bool
    {
        if (! $this->supports($model) || ! $model->hasEmail() || $model->hasVerifiedEmail()) {
            return false;
        }

        if (! $force && $this->sentRecently($model)) {
            return false;
        }

        $email = (string) $model->email;
        $rawToken = Str::random(64);
        $ttlHours = $this->ttlHours();

        // Na jednu adresu jeden platný odkaz — staršie sa zahadzujú, aby
        // preposlaný starý e-mail neostal použiteľný po vydaní nového.
        $model->emailVerifications()->delete();

        $model->emailVerifications()->create([
            'email' => $email,
            'token' => hash('sha256', $rawToken),
            'sent_at' => now(),
            'expires_at' => now()->addHours($ttlHours),
        ]);

        Notification::route('mail', $email)
            ->notify(new ContactEmailVerificationRequest(
                token: $rawToken,
                ttlHours: $ttlHours,
                subjectName: $this->subjectName($model),
                subjectType: __('mail.contact_email_verification.types.' . ContactEmailVerification::aliasFor($model)),
                email: $email,
            ));

        return true;
    }

    /**
     * Uplatní odkaz z e-mailu.
     *
     * @return Model|null Overený model, alebo `null` pri neplatnom, prepísanom
     *                    či prepadnutom odkaze — volajúci ich nerozlišuje
     *                    zámerne, aby sa cez odpoveď nedali tokeny skúšať.
     */
    public function verify(string $rawToken): ?Model
    {
        $record = ContactEmailVerification::query()
            ->where('token', hash('sha256', $rawToken))
            ->first();

        if (! $record) {
            return null;
        }

        if ($record->isExpired()) {
            $record->delete();

            return null;
        }

        $model = $record->verifiable;

        // Adresa sa medzitým vo formulári zmenila — starý odkaz nesmie overiť
        // novú adresu, na ktorú nikto nič neposielal.
        if (! $model || ! $this->supports($model) || (string) $model->email !== $record->email) {
            $record->delete();

            return null;
        }

        $model->markEmailAsVerified();
        $model->emailVerifications()->delete();

        return $model;
    }

    /** Kedy najskôr smie odísť ďalší e-mail, ak práve beží čakacia lehota. */
    public function retryAfter(Model $model): ?Carbon
    {
        if (! $this->supports($model)) {
            return null;
        }

        return $this->retryAfterFor($model->pendingEmailVerification());
    }

    /**
     * To isté z už načítaného záznamu — pre volajúcich, ktorí ho v ruke majú
     * (resource), aby sa naň nedotazovali druhýkrát.
     */
    public function retryAfterFor(?ContactEmailVerification $pending): ?Carbon
    {
        $sentAt = $pending?->sent_at;

        if ($sentAt === null) {
            return null;
        }

        $retryAt = $sentAt->copy()->addMinutes($this->cooldownMinutes());

        return $retryAt->isFuture() ? $retryAt : null;
    }

    /** Používa model trait HasVerifiableEmail a je vo whitelistse typov? */
    public function supports(Model $model): bool
    {
        return ContactEmailVerification::aliasFor($model) !== null
            && method_exists($model, 'hasVerifiedEmail');
    }

    private function sentRecently(Model $model): bool
    {
        return $this->retryAfter($model) !== null;
    }

    /** Názov, pod ktorým adresát v e-maile spozná, čoho sa overenie týka. */
    private function subjectName(Model $model): string
    {
        return (string) ($model->name ?? $model->title ?? '');
    }

    private function ttlHours(): int
    {
        return max(1, (int) config('contact_email.verification_ttl_hours', 72));
    }

    private function cooldownMinutes(): int
    {
        return max(0, (int) config('contact_email.resend_cooldown_minutes', 5));
    }
}
