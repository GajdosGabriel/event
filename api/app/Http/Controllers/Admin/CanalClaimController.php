<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CanalClaimStatus;
use App\Http\Controllers\Controller;
use App\Models\CanalClaim;
use App\Services\Canals\CanalClaims;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fronta žiadostí o prevzatie kanála: posúdenie žiadostí bez overenia
 * kontaktom a rozhodnutie o napadnutých prevzatiach.
 */
class CanalClaimController extends Controller
{
    public function __construct(
        private CanalClaims $claims,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(CanalClaimStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Predvolene to, čo čaká na rozhodnutie admina.
        $statuses = isset($data['status'])
            ? [$data['status']]
            : [CanalClaimStatus::Pending->value, CanalClaimStatus::Contested->value];

        $page = CanalClaim::query()
            ->with(['canal:id,name,slug,email', 'user:id,email', 'decidedBy:id,email'])
            ->whereIn('status', $statuses)
            ->latest('id')
            ->paginate($data['per_page'] ?? 50);

        $page->getCollection()->transform(fn (CanalClaim $claim) => [
            'id' => $claim->id,
            'canal' => $claim->canal ? ['id' => $claim->canal->id, 'name' => $claim->canal->name, 'email' => $claim->canal->email] : null,
            'user' => $claim->user ? ['id' => $claim->user->id, 'email' => $claim->user->email, 'name' => $claim->user->displayName()] : null,
            'method' => $claim->method->value,
            // Čakajúca po platnosti je v skutočnosti prepadnutá.
            'status' => $claim->status === CanalClaimStatus::Pending && ! $claim->isPending()
                ? CanalClaimStatus::Expired->value
                : $claim->status->value,
            'contact_email' => $claim->contact_email,
            'message' => $claim->message,
            'contest_note' => $claim->contest_note,
            'decision_note' => $claim->decision_note,
            'decided_by' => $claim->decidedBy?->email,
            'created_at' => $claim->created_at,
            'expires_at' => $claim->expires_at,
            'completed_at' => $claim->completed_at,
            'contested_at' => $claim->contested_at,
        ]);

        return response()->json($page);
    }

    public function approve(Request $request, CanalClaim $claim): JsonResponse
    {
        $this->claims->approve($claim, $request->user(), $this->note($request));

        return response()->json(['data' => ['status' => 'completed']]);
    }

    public function reject(Request $request, CanalClaim $claim): JsonResponse
    {
        $this->claims->reject($claim, $request->user(), $this->note($request));

        return response()->json(['data' => ['status' => 'rejected']]);
    }

    /** Napadnuté prevzatie: `revert=true` vráti kanál, inak sa námietka zamietne. */
    public function resolve(Request $request, CanalClaim $claim): JsonResponse
    {
        $revert = (bool) $request->validate(['revert' => ['required', 'boolean']])['revert'];

        $this->claims->resolve($claim, $request->user(), $revert, $this->note($request));

        return response()->json(['data' => ['status' => $revert ? 'reverted' : 'completed']]);
    }

    private function note(Request $request): ?string
    {
        return $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
    }
}
