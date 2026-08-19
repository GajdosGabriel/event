<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionStoreRequest;
use App\Models\Event;
use App\Models\Subscription;
use App\Notifications\SubscriptionConfirmed;
use App\Repositories\Contracts\EventRepository;
use App\Support\SubmissionTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

/**
 * „Pripomeň mi" — odber podujatia bez účtu.
 *
 * Existuje preto, že na bezplatnom podujatí bez lístkov sa dnes nedá spraviť
 * vôbec nič: registračná sekcia aj mobilná lišta sú skryté a návštevníkovi
 * zostane „Kopírovať odkaz". Toto je najmenší možný záväzok medzi „pozrel som
 * si to" a „objednal som lístok" — jedno pole a žiadny účet.
 *
 * Autorizácia je rovnaká konvencia ako pri RSVP a nástenke otázok: token
 * v odkaze JE autorizácia. Odhlásenie preto nepotrebuje prihlásenie.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        protected EventRepository $eventRepository,
    ) {
    }

    /**
     * Známka, že sa formulár naozaj otvoril. Vydáva sa až kliknutím na tlačidlo,
     * takže bot, ktorý našiel adresu POSTu, ju nemá odkiaľ vziať — rovnaká
     * poistka ako pri otázkach, popísaná v SubmissionTicket.
     */
    public function ticket(int|string $event): JsonResponse
    {
        $this->publicEventOrFail($event);

        return response()->json([
            'ticket' => SubmissionTicket::issue('subscription:' . $event),
        ]);
    }

    public function store(SubscriptionStoreRequest $request, int|string $event): JsonResponse
    {
        $model = $this->publicEventOrFail($event);

        // Podujatie, ktoré sa už začalo, nemá čo pripomínať.
        if ($model->start_at !== null && $model->start_at->isPast()) {
            abort(422, __('subscriptions.errors.event_started'));
        }

        $email = $request->subscriptionEmail();

        // Dvakrát odoslaný formulár (netrpezlivý klik, obnovená stránka) nesmie
        // založiť druhý odber ani poslať druhý e-mail. Unikátny index to ustráži
        // aj pri súbehu, firstOrCreate len ušetrí výnimku v bežnom prípade.
        $subscription = Subscription::query()->firstOrNew([
            'subscribable_type' => Event::class,
            'subscribable_id' => $model->id,
            'email' => $email,
        ]);

        // Odhlásený riadok má `email` NULL, takže sa sem nikdy nenájde a kto sa
        // hlási znova, dostane nový riadok s novým tokenom. To je zámer: ten
        // starý už poslal do sveta v pätičke a nemá sa oživovať.
        if (! $subscription->exists) {
            $subscription->forceFill([
                'token' => Subscription::freshToken(),
                'locale' => $request->subscriptionLocale(),
                'confirmed_at' => now(),
            ])->save();

            Notification::route('mail', $email)
                ->notify(new SubscriptionConfirmed($model, $subscription));
        }

        // Rovnaká odpoveď pre nový aj existujúci odber. Rozdiel by z endpointu
        // spravil overovač, či daná adresa podujatie sleduje.
        return response()->json(['status' => 'subscribed'], 201);
    }

    /**
     * Čo vlastne odhlasujem. Token je autorizácia, takže sa smie ukázať názov
     * podujatia — nie však adresa, na ktorú odber beží.
     */
    public function show(string $token): JsonResponse
    {
        $subscription = $this->byTokenOrFail($token);

        return response()->json([
            'event' => $this->targetName($subscription),
            'active' => $subscription->isActive(),
        ]);
    }

    /**
     * Odhlásenie je idempotentné: odkaz z pätičky si klient môže prednačítať
     * a človek ho môže otvoriť dvakrát. Druhý raz sa teda nesmie stať chyba.
     */
    public function destroy(string $token): JsonResponse
    {
        $subscription = $this->byTokenOrFail($token);

        $subscription->unsubscribe();

        return response()->json([
            'event' => $this->targetName($subscription),
            'active' => false,
        ]);
    }

    private function byTokenOrFail(string $token): Subscription
    {
        // Cieľ sa načítava rovno v dopyte — `Model::preventLazyLoading` je mimo
        // produkcie zapnuté a doťahovať ho až v `targetName()` by hodilo výnimku.
        $subscription = Subscription::query()->with('subscribable')->where('token', $token)->first();

        if ($subscription === null) {
            abort(404);
        }

        return $subscription;
    }

    /**
     * Viditeľnosť rieši `publicShow()` — rovnako ako verejný detail, takže sa
     * nedá odoberať koncept ani naplánované podujatie.
     */
    private function publicEventOrFail(int|string $event): Event
    {
        $model = $this->eventRepository->publicShow($event);

        if ($model === null) {
            abort(404);
        }

        return $model;
    }

    private function targetName(Subscription $subscription): ?string
    {
        return $subscription->subscribable?->name;
    }
}
