<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Account\AccountClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Príjem udalostí z Accountu.
 *
 * Bez neho by sa zmena fakturačných údajov v inom projekte prejavila
 * v Evente až po vypršaní cache (`ACCOUNT_ORGANIZATION_TTL`).
 *
 * Endpoint sa v Accounte registruje v „API a webhooky“ a musí odoberať
 * aspoň `organization.updated` a `organization.deleted`.
 */
class AccountWebhookController extends Controller
{
    public function __construct(private readonly AccountClient $account) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('account.webhook_secret');

        if ($secret === '') {
            // Nenastavené tajomstvo znamená, že podpis nevieme overiť. Prijať
            // takú požiadavku by znamenalo pustiť dnu hocikoho, kto adresu uhádne.
            return response()->json(['message' => 'Webhooky z Accountu nie sú nastavené.'], 503);
        }

        $timestamp = (int) $request->header('X-Accounts-Timestamp');
        $signature = (string) $request->header('X-Accounts-Signature');
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Neplatný podpis.'], 400);
        }

        // Ochrana proti replay útoku — odchytený request nesmie ísť použiť neskôr.
        if (abs(time() - $timestamp) > 300) {
            return response()->json(['message' => 'Zastaraná požiadavka.'], 400);
        }

        $uuid = $request->input('data.organization_id')
            ?? $request->input('data.organization.id');

        if ($uuid) {
            $this->account->forget($uuid);

            if ($request->input('event') === 'organization.deleted') {
                // Firma v Accounte zanikla. Lokálny profil organizátora
                // zostáva — zmizne len väzba na fakturačné údaje, aby
                // Event neukazoval niečo, čo už neexistuje.
                Organization::where('account_uuid', $uuid)
                    ->update(['account_uuid' => null, 'account_synced_at' => null]);
            }

            Log::info('Account webhook', ['event' => $request->input('event'), 'organization' => $uuid]);
        }

        return response()->json(['ok' => true]);
    }
}
