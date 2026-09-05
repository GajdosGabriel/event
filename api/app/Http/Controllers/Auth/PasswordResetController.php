<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordForgotRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Obnova zabudnutého hesla.
 *
 * Stojí na Laravelovom `Password` brokeri (tabuľka `password_reset_tokens`,
 * platnosť a throttling v `config/auth.php`) — vlastný tokenový mechanizmus ako
 * pri `pending_registrations` by tu len zopakoval to isté horšie.
 *
 * Odlišné od Laravel skeletonu sú dve veci: odkaz vedie na stránku SPA, nie na
 * blade formulár ([PasswordResetLink]), a odpoveď na `forgot` je vždy rovnaká.
 */
class PasswordResetController extends Controller
{
    /**
     * Pošle odkaz na obnovu — ak je komu.
     *
     * Odpoveď je zámerne rovnaká pre existujúcu aj neexistujúcu adresu vrátane
     * HTTP kódu a času: rozdiel by z formulára spravil overovač, kto na portáli
     * má účet. To isté platí pre stav `RESET_THROTTLED` (broker púšťa jeden
     * e-mail za minútu na používateľa) — priznať ho znamená priznať existenciu.
     *
     * Nepošle sa nič ani vtedy, keď adresa čaká na overenie registrácie
     * (`pending_registrations`): používateľ ešte nemá riadok v `users`, takže
     * nemá čo obnovovať — potrebuje overovací e-mail, nie tento.
     */
    public function forgot(PasswordForgotRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            // Do odpovede sa to nedostane, ale pri hlásení „e-mail neprišiel“
            // je to jediné, z čoho sa dá zistiť prečo.
            Log::info('Password reset link not sent', [
                'status' => $status,
                'email' => $request->input('email'),
            ]);
        }

        // `passwords.sent_blind`, nie Laravelovo `passwords.sent`: to je
        // formulované ako potvrdenie odoslania („poslali sme vám"), čo pri
        // neznámej adrese klame a pri známej ju potvrdzuje.
        return response()->json([
            'message' => __('passwords.sent_blind'),
        ]);
    }

    /**
     * Nastaví nové heslo podľa tokenu z e-mailu.
     */
    public function reset(PasswordResetRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Kto sa dostal k starému heslu, mohol si vyrobiť Bearer token.
                // Obnova hesla je preto aj odhlásením zo všetkých zariadení —
                // inak by zmena hesla útočníka nevyhodila.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __('passwords.reset'),
            ]);
        }

        // Vypršaný alebo už použitý token je najčastejší koniec tohto toku
        // (odkaz platí hodinu a je jednorazový), preto má vlastnú hlášku pri
        // poli `token` — SPA podľa nej ponúkne poslanie nového odkazu.
        if ($status === Password::INVALID_TOKEN) {
            return response()->json([
                'message' => __('passwords.token'),
                'errors' => ['token' => [__('passwords.token')]],
            ], 422);
        }

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => __('passwords.throttled'),
            ], 429);
        }

        return response()->json([
            'message' => __('passwords.user'),
            'errors' => ['email' => [__('passwords.user')]],
        ], 422);
    }
}
