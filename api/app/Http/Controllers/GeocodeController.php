<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddressGeocodeRequest;
use App\Services\Geocoding\AddressGeocoder;
use Illuminate\Http\JsonResponse;

/**
 * Poloha z rozpísanej adresy pre ktorýkoľvek editor adresy (miesto, kanál).
 *
 * Mapa sa posunie hneď po výbere obce a spresní sa, keď pribudne ulica —
 * nečaká sa na uloženie ani na detekciu. Zdroj dát je verejný geokóder, takže
 * endpoint nie je viazaný na právo k miestu ani ku kanálu; stačí prihlásenie
 * a limit požiadaviek na route.
 */
class GeocodeController extends Controller
{
    public function __invoke(AddressGeocodeRequest $request, AddressGeocoder $geocoder): JsonResponse
    {
        return response()->json($geocoder->resolve(
            $request->municipalityId(),
            $request->input('street'),
            $request->input('postcode'),
            $request->input('country'),
        ));
    }
}
