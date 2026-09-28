<?php

namespace App\Http\Controllers\Public;

use App\Enums\CanalClaimMethod;
use App\Http\Controllers\Controller;
use App\Models\Canal;
use App\Models\CanalClaim;
use App\Services\Canals\CanalClaims;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Prevzatie kanála z verejnej stránky (docs/canal-ownership.md, fáza 3).
 *
 *  - `store`   — prihlásený používateľ žiada o kanál (overenie cez kontaktnú
 *                schránku alebo posúdenie administrátorom),
 *  - `show`/`confirm` — kontaktná schránka žiadosť posúdi; autorizáciou je
 *                token z e-mailu, prihlásenie netreba (potvrdiť môže sekretárka
 *                za predsedu),
 *  - `contestShow`/`contest` — kontaktná adresa napadne dokončené prevzatie.
 */
class CanalClaimController extends Controller
{
    public function __construct(
        private CanalClaims $claims,
    ) {}

    public function store(Request $request, Canal $canal): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in([CanalClaimMethod::ContactEmail->value, CanalClaimMethod::AdminReview->value])],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $claim = $this->claims->request($canal, $request->user(), CanalClaimMethod::from($data['method']), $data['message'] ?? null);

        return response()->json(['data' => [
            'id' => $claim->id,
            'method' => $claim->method->value,
            'status' => $claim->status->value,
        ]], 201);
    }

    public function show(string $token): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->byToken('token', $token))]);
    }

    public function confirm(string $token): JsonResponse
    {
        $canal = $this->claims->confirm($this->byToken('token', $token));

        return response()->json(['data' => ['status' => 'completed', 'canal' => ['id' => $canal->id, 'name' => $canal->name]]]);
    }

    public function contestShow(string $token): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->byToken('contest_token', $token))]);
    }

    public function contest(Request $request, string $token): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $this->claims->contest($this->byToken('contest_token', $token), $data['note'] ?? null);

        return response()->json(['data' => ['status' => 'contested']]);
    }

    private function byToken(string $column, string $token): CanalClaim
    {
        return CanalClaim::query()->with(['canal', 'user'])->where($column, $token)->firstOrFail();
    }

    /**
     * Čo vidí držiteľ odkazu: kto žiada a o čo. Žiadateľovu adresu len
     * maskovane — odkaz sa dá preposlať.
     */
    private function payload(CanalClaim $claim): array
    {
        return [
            'canal' => ['id' => $claim->canal?->id, 'name' => $claim->canal?->name],
            'requester' => [
                'name' => $claim->user?->displayName(),
                'email' => $claim->user?->maskedEmail(),
            ],
            'method' => $claim->method->value,
            'message' => $claim->message,
            'status' => $claim->isPending() ? 'pending' : $claim->status->value,
            'expires_at' => $claim->expires_at,
            'contest_until' => $claim->contest_until,
            'contestable' => $claim->isContestable(),
        ];
    }
}
