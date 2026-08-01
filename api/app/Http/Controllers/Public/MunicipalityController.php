<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Municipality;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Číselník obcí pre verejné formuláre — dnes výhradne pre sprievodcu nahratím
 * plagátu, kde mesto vyberá človek bez účtu.
 *
 * Dashboardová `municipalities/all` je za `auth:sanctum`, takže sa použiť nedá.
 * Vraciame len `id` a `name`: zvyšok číselníka (PSČ, skratky, časové značky)
 * je pre výber v selecte zbytočný a nemá dôvod byť verejný.
 */
class MunicipalityController extends Controller
{
    /** Zhodí ju zmena číselníka — viď App\Observers\MunicipalityObserver. */
    public const CACHE_KEY = 'public.municipalities.lookup';

    public function index(): JsonResponse
    {
        // Zoznam má tisíce položiek a mení sa rádovo raz za roky — bez cache by
        // ho ťahal z DB každý, kto otvorí sprievodcu.
        $municipalities = Cache::remember(
            self::CACHE_KEY,
            now()->addDay(),
            fn () => Municipality::query()
                ->where('use', true)
                ->orderBy('fullname')
                ->get(['id', 'fullname'])
                ->map(fn (Municipality $m) => [
                    'id' => (int) $m->id,
                    'name' => (string) $m->fullname,
                ])
                ->all(),
        );

        return response()->json(['data' => $municipalities]);
    }
}
