<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContactEmailVerification;
use App\Services\Contacts\ContactEmailVerifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Potvrdenie kontaktného e-mailu z odkazu a jeho opätovné odoslanie.
 *
 * Generické pre všetky typy z whitelistu ContactEmailVerification::TARGETS —
 * pridanie ďalšieho modelu s kontaktnou adresou nevyžaduje ani novú routu,
 * ani nový controller.
 */
class ContactEmailVerificationController extends Controller
{
    public function __construct(
        private readonly ContactEmailVerifier $verifier,
    ) {
    }

    /**
     * Uplatní odkaz z e-mailu. Bez prihlásenia — adresu potvrdzuje jej
     * majiteľ, ktorý účet v portáli mať nemusí. Autorizáciou je samotný token.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        $model = $this->verifier->verify($validated['token']);

        if (! $model) {
            return response()->json([
                'message' => __('contact_email.verify.invalid'),
                'code' => 'invalid_token',
            ], 404);
        }

        return response()->json([
            'message' => __('contact_email.verify.done'),
            'data' => [
                'type' => ContactEmailVerification::aliasFor($model),
                'id' => $model->getKey(),
                'name' => $model->name ?? $model->title ?? null,
                'email' => $model->email,
            ],
        ]);
    }

    /**
     * „Poslať znova" z formulára. Na rozdiel od potvrdenia vyžaduje účet
     * s právom model upravovať — inak by sa dala routa zneužiť na posielanie
     * e-mailov na cudzie adresy podľa id.
     */
    public function resend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(ContactEmailVerification::TARGETS))],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $model = $this->resolveTarget($validated['type'], (int) $validated['id']);

        $this->authorize('update', $model);

        if (! $model->hasEmail()) {
            return response()->json([
                'message' => __('contact_email.resend.no_email'),
                'code' => 'no_email',
            ], 422);
        }

        if ($model->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('contact_email.resend.already_verified'),
                'code' => 'already_verified',
            ], 409);
        }

        $retryAfter = $this->verifier->retryAfter($model);

        if ($retryAfter !== null) {
            return response()->json([
                'message' => __('contact_email.resend.too_soon', ['minutes' => max(1, (int) ceil(now()->diffInSeconds($retryAfter) / 60))]),
                'code' => 'too_soon',
                'retry_after' => $retryAfter->toIso8601String(),
            ], 429);
        }

        $this->verifier->issue($model, force: true);

        return response()->json([
            'message' => __('contact_email.resend.sent', ['email' => $model->email]),
            'data' => ['email' => $model->email],
        ], 202);
    }

    private function resolveTarget(string $type, int $id): Model
    {
        /** @var class-string<Model> $class */
        $class = ContactEmailVerification::TARGETS[$type];

        return $class::query()->findOrFail($id);
    }
}
