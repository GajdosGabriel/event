<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Support\PublicUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Odbery „daj mi vedieť", ktoré účet vytvoril.
 *
 * Odber vzniká **bez účtu** — jediné, čo o odberateľovi vieme, je adresa
 * ([Subscription]). Preto sa tu páruje cez e-mail, nie cez `user_id`: kto si
 * odbery vypýtal ešte pred registráciou, ich tu nájde tiež.
 *
 * Odhlásiť sa dá aj naďalej odkazom z pätičky e-mailu (`/odhlasenie/{token}`) —
 * ten funguje bez prihlásenia a je jediná cesta pre toho, kto účet nemá. Táto
 * routa je pohodlie navyše, nie jeho náhrada, a odhlásenie robí tou istou
 * metódou modelu (adresa sa zahodí, riadok zostane).
 */
class SubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $subscriptions = $this->ownQuery($request)
            ->with('subscribable')
            ->latest('id')
            ->get()
            ->map(fn (Subscription $subscription) => $this->present($subscription))
            ->values();

        return response()->json(['data' => $subscriptions]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $subscription = $this->ownQuery($request)->whereKey($id)->firstOrFail();

        $subscription->unsubscribe();

        return response()->json(['status' => 'ok']);
    }

    /**
     * Živé odbery patriace adrese účtu. Odhlásený riadok má `email` NULL, takže
     * `active()` ho vynechá a `destroy()` sa naň nedostane ani opakovaným
     * volaním — druhý klik skončí na 404, nie na chybe.
     */
    private function ownQuery(Request $request): Builder
    {
        $email = mb_strtolower(trim((string) $request->user()->email));

        if ($email === '') {
            return Subscription::query()->whereRaw('1 = 0');
        }

        return Subscription::query()
            ->active()
            ->whereRaw('LOWER(email) = ?', [$email]);
    }

    /**
     * Odber sám o sebe je len adresa a token — pre výpis treba vedieť, čoho sa
     * týka. Cieľ je morph (dnes podujatie alebo kanál), takže tvar zjednocujeme
     * tu a nie v resource triede pre každý typ zvlášť.
     *
     * @return array<string, mixed>
     */
    private function present(Subscription $subscription): array
    {
        $target = $subscription->subscribable;
        $alias = array_search($subscription->subscribable_type, Subscription::TARGETS, true) ?: null;

        return [
            'id' => $subscription->id,
            'type' => $alias,
            'created_at' => $subscription->created_at,
            'target' => $target === null ? null : [
                'id' => $target->getKey(),
                'name' => $target->name ?? null,
                'start_at' => $target->start_at ?? null,
                'url' => match ($alias) {
                    'event' => PublicUrl::event($target),
                    'canal' => PublicUrl::canal($target),
                    default => null,
                },
            ],
        ];
    }
}
