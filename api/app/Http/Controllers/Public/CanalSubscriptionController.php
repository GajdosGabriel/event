<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionStoreRequest;
use App\Models\Canal;
use App\Models\Subscription;
use App\Notifications\CanalFollowConfirmation;
use App\Repositories\Contracts\CanalRepository;
use App\Support\SubmissionTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

/**
 * „Sledovať organizátora" — e-mail, keď kanál zverejní novú akciu.
 *
 * Na rozdiel od „Pripomeň mi" (jedno podujatie, odber platí hneď) tu ide
 * o opakované e-maily bez konca, preto **dvojité potvrdenie**: kým človek
 * neklikne na odkaz z prvého e-mailu, nedostane nič ďalšie. Inak by ktokoľvek
 * mohol cudziu adresu prihlásiť na pravidelnú poštu.
 *
 * Výnimka: prihlásený používateľ s overenou adresou, ktorý sleduje na adresu
 * svojho účtu — tú sme už overili, druhý e-mail by bol len trenie.
 */
class CanalSubscriptionController extends Controller
{
    public function __construct(
        protected CanalRepository $canals,
    ) {}

    /** Známka, že sa formulár naozaj otvoril — viď SubmissionTicket. */
    public function ticket(int|string $canal): JsonResponse
    {
        $this->publicCanalOrFail($canal);

        return response()->json([
            'ticket' => SubmissionTicket::issue(SubscriptionStoreRequest::ticketScope(null, $canal)),
        ]);
    }

    public function store(SubscriptionStoreRequest $request, int|string $canal): JsonResponse
    {
        $model = $this->publicCanalOrFail($canal);
        $email = $request->subscriptionEmail();
        $trusted = $this->isOwnVerifiedAddress($email);

        $subscription = Subscription::query()->firstOrNew([
            'subscribable_type' => Canal::class,
            'subscribable_id' => $model->id,
            'email' => $email,
        ]);

        if (! $subscription->exists) {
            $subscription->forceFill([
                'token' => Subscription::freshToken(),
                'locale' => $request->subscriptionLocale(),
                'confirmed_at' => $trusted ? now() : null,
            ])->save();

            $this->sendConfirmation($model, $subscription);
        } elseif ($subscription->confirmed_at === null) {
            if ($trusted) {
                $subscription->forceFill(['confirmed_at' => now()])->save();
            } elseif ($subscription->updated_at === null || $subscription->updated_at->lt(now()->subHour())) {
                // Potvrdenie sa stratilo v spame — pošleme znova, ale najviac
                // raz za hodinu, aby sa z formulára nedalo cudziu schránku zahltiť.
                $subscription->touch();
                $this->sendConfirmation($model, $subscription);
            }
        }

        // Odpoveď nezávisí od toho, či adresa kanál už sledovala — inak by
        // endpoint prezradil, kto čo odoberá. Hosť dostane vždy „pozrite e-mail".
        return response()->json(['status' => $trusted ? 'subscribed' : 'pending'], 201);
    }

    private function sendConfirmation(Canal $canal, Subscription $subscription): void
    {
        $notification = new CanalFollowConfirmation($canal, $subscription);

        if ($subscription->locale) {
            $notification->locale($subscription->locale);
        }

        Notification::route('mail', $subscription->email)->notify($notification);
    }

    private function isOwnVerifiedAddress(string $email): bool
    {
        $user = auth('sanctum')->user();

        return $user !== null
            && $user->email_verified_at !== null
            && mb_strtolower(trim((string) $user->email)) === $email;
    }

    /** Koncept ani blokovaný kanál sa sledovať nedá — rovnako ako verejný profil. */
    private function publicCanalOrFail(int|string $canal): Canal
    {
        $model = ctype_digit((string) $canal) ? $this->canals->publicShow($canal) : null;

        abort_unless($model instanceof Canal, 404);

        return $model;
    }
}
