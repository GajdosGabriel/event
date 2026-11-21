<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Repositories\Contracts\EventRepository;
use App\Services\Tickets\QrCodeGenerator;
use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * QR kód pozvánky na akciu — na nástenku, leták alebo plátno.
 *
 * Verejné zámerne: kód nesie len verejnú adresu akcie, takže tajiť ho nemá
 * zmysel a náhľad v dashboarde je obyčajný `<img>`. Viditeľnosť rieši
 * `publicShow()` rovnako ako verejný detail — koncept ani akcia v koši kód
 * nedostanú, aby sa nevytlačil odkaz, ktorý zatiaľ vedie na 404.
 *
 * Nikde sa neukladá, vzniká pri každom stiahnutí (rovnako ako QR vstupeniek).
 */
class EventQrController extends Controller
{
    public function __construct(
        private EventRepository $events,
        private QrCodeGenerator $qrCodes,
    ) {}

    public function png(Request $request, string $id): Response
    {
        $event = $this->event($id);

        $size = max(120, min(2000, (int) $request->query('size', 1200)));
        // Tichá zóna 4 moduly ≈ 6 % pri bežnej dĺžke adresy; bez nej časť
        // čítačiek kód nenájde, keď ho niekto vloží na farebné pozadie.
        $image = $this->qrCodes->imageForUrl(PublicUrl::eventInvite($event), $size, (int) round($size * 0.06));

        ob_start();
        imagepng($image, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $this->respond($request, $png, 'image/png', $this->filename($event, 'png'));
    }

    public function svg(Request $request, string $id): Response
    {
        $event = $this->event($id);

        $svg = $this->qrCodes->svgForUrl(PublicUrl::eventInvite($event));

        return $this->respond($request, $svg, 'image/svg+xml', $this->filename($event, 'svg'));
    }

    private function event(string $id): Event
    {
        $event = ctype_digit($id) ? $this->events->publicShow($id) : null;

        abort_unless($event instanceof Event, 404);

        return $event;
    }

    private function filename(Event $event, string $extension): string
    {
        // Str::slug prechádza cez Str::ascii — hlavička ostane čisté ASCII.
        $slug = trim(Str::slug($event->name), '-');

        return 'qr-'.($slug !== '' ? Str::limit($slug, 60, '') : 'akcia-'.$event->id).'.'.$extension;
    }

    /** `?download=1` vynúti uloženie súboru; bez neho je to náhľad do `<img>`. */
    private function respond(Request $request, string $bytes, string $mime, string $filename): Response
    {
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            // Adresa sa mení len so zapnutím registrácie, takže desať minút
            // cache nikomu neublíži a ušetrí opakované vykresľovanie náhľadu.
            'Cache-Control' => 'public, max-age=600',
        ]);
    }
}
