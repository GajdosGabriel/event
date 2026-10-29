<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CanalRepository;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\VenueRepository;
use App\Services\Views\ViewRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Započítanie zobrazenia verejného detailu podujatia, miesta alebo kanála.
 *
 * Zámerne oddelené od GET detailu: front tento POST pošle až po pár sekundách
 * viditeľnej stránky. Crawler bez JavaScriptu, prefetch, opakované načítanie
 * dát ani headless nástroj, ktorý stránku len otvorí a hneď odíde, sa tak
 * nezapočítajú.
 *
 * Odpoveď je vždy 204 — aj keď sa zobrazenie nezapočítalo. Volajúci sa
 * nemá ako dozvedieť, prečo, a teda ani ako filter obísť.
 */
class ViewController extends Controller
{
    public function __invoke(
        string $resource,
        $id,
        Request $request,
        ViewRecorder $viewRecorder,
        EventRepository $events,
        VenueRepository $venues,
        CanalRepository $canals,
    ): Response {
        // publicShow — rovnaká viditeľnosť ako verejný detail, takže sa nedá
        // nafúknuť počítadlo nepublikovaného záznamu.
        $model = match ($resource) {
            'events' => $events->publicShow($id),
            'venues' => $venues->publicShow($id),
            'canals' => $canals->publicShow($id),
        };

        if (! $model) {
            abort(404);
        }

        $viewRecorder->record($model, $request);

        return response()->noContent();
    }
}
