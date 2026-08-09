<?php

namespace App\Http\Resources\Traits;

use App\Services\Contacts\ContactEmailVerifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Stav kontaktného e-mailu pre front — či je adresa overená a či sa dá
 * overenie poslať znova.
 *
 * `verified` chodí vždy (číta sa zo stĺpca, nič nestojí), detaily
 * o rozpracovanom overení len na detaile modelu a len tomu, kto ho smie
 * upravovať: na výpise by dotaz na čakajúce overenie bežal pre každý riadok
 * (rovnaký dôvod, prečo sa tam nepočíta ani `contactable`).
 */
trait HasContactEmailState
{
    /** @return array<string, mixed>|null `null`, keď model adresu nemá */
    protected function contactEmailState(Request $request): ?array
    {
        $model = $this->resource;
        $verifier = app(ContactEmailVerifier::class);

        if (! $model instanceof Model || ! $verifier->supports($model) || ! $model->hasEmail()) {
            return null;
        }

        $verified = $model->hasVerifiedEmail();
        $state = ['verified' => $verified];

        $isDetail = $request->route()?->getActionMethod() === 'show';
        $canManage = $request->user()?->can('update', $model) ?? false;

        if ($verified || ! $isDetail || ! $canManage) {
            return $state;
        }

        $pending = $model->pendingEmailVerification();
        $retryAfter = $verifier->retryAfterFor($pending);

        return $state + [
            'pending' => $pending !== null,
            'sent_at' => $pending?->sent_at?->toIso8601String(),
            'can_resend' => $retryAfter === null,
            'retry_after' => $retryAfter?->toIso8601String(),
        ];
    }
}
