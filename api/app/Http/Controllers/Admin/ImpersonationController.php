<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\SystemLog\Recorder;
use App\Support\Impersonation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Super-admin sa prihlási do cudzieho účtu, aby videl presne to, čo on —
 * na kontrolu a hľadanie chýb. Každý vstup aj návrat ide do denníka udalostí.
 */
class ImpersonationController extends Controller
{
    public function store(Request $request, User $user): JsonResponse
    {
        $admin = $request->user();

        // Z cudzieho účtu sa ďalej neskáče — návrat musí viesť k adminovi.
        abort_if(Impersonation::active($admin), 403, __('users.errors.impersonate_nested'));
        abort_if((int) $user->id === (int) $admin->id, 403, __('users.errors.impersonate_self'));
        // Iný super-admin by znamenal konať pod menom kolegu s rovnakými právami.
        abort_if($user->hasRole('super-admin'), 403, __('users.errors.impersonate_admin'));

        $token = $user->createToken(
            Impersonation::tokenName($admin),
            ['*'],
            now()->addHours(Impersonation::TTL_HOURS),
        )->plainTextToken;

        // Sanctum uprednostní reláciu (cookie) pred tokenom — kým by v nej bol
        // admin, API by naďalej odpovedalo za neho. Vlastný token si drží front.
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Recorder::info('admin', 'impersonation_started', 'Prihlásenie do cudzieho účtu',
            status: 'ok', userId: $admin->id, subject: $user, ip: $request->ip());

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $adminId = Impersonation::impersonatorId($user);

        abort_if($adminId === null, 422, __('users.errors.impersonate_inactive'));

        $user->currentAccessToken()->delete();

        Recorder::info('admin', 'impersonation_stopped', 'Návrat z cudzieho účtu',
            status: 'ok', userId: $adminId, subject: $user, ip: $request->ip());

        return response()->json(['message' => 'Impersonation stopped']);
    }
}
