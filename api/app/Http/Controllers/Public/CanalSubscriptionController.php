<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionStoreRequest;
use App\Models\Canal;
use App\Repositories\Contracts\CanalRepository;
use App\Services\Canals\CanalFollowing;
use App\Support\SubmissionTicket;
use Illuminate\Http\JsonResponse;

/**
 * Tlačidlo „Sledovať organizátora" — e-mail, keď kanál zverejní novú akciu.
 *
 * Sledovanie platí hneď, bez potvrdzovacieho e-mailu (pravidlá a dôvod
 * v CanalFollowing). Ochrana proti botom je tá istá ako pri „Pripomeň mi":
 * známka z otvorenia formulára a honeypot (SubscriptionStoreRequest).
 */
class CanalSubscriptionController extends Controller
{
    public function __construct(
        protected CanalRepository $canals,
        protected CanalFollowing $following,
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

        $this->following->follow($model, $request->subscriptionEmail(), $request->subscriptionLocale());

        // Rovnaká odpoveď pre nový aj existujúci odber — inak by endpoint
        // prezradil, kto čo sleduje.
        return response()->json(['status' => 'subscribed'], 201);
    }

    /** Koncept ani blokovaný kanál sa sledovať nedá — rovnako ako verejný profil. */
    private function publicCanalOrFail(int|string $canal): Canal
    {
        $model = ctype_digit((string) $canal) ? $this->canals->publicShow($canal) : null;

        abort_unless($model instanceof Canal, 404);

        return $model;
    }
}
