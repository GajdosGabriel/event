<?php

namespace App\Http\Controllers\Public;

use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Models\Canal;
use App\Models\Event;
use App\Repositories\Contracts\CanalRepository;
use App\Services\Canals\CanalClaims;
use App\Services\Canals\CanalContactVerifier;
use App\Services\Views\ViewRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CanalController extends Controller
{
    protected $canalRepository;

    public function __construct(CanalRepository $canalRepository)
    {
        $this->canalRepository = $canalRepository;
    }

    public function index()
    {
        return response()->json($this->canalRepository->publicIndex());
    }

    /**
     * Odkaz z e-mailu po zmene kontaktu (CanalContactVerifier). Podpis overil
     * `signed` middleware; výsledok sa ukáže na verejnej stránke kanála.
     */
    public function verifyContact(Request $request, Canal $canal, CanalContactVerifier $verifier)
    {
        $ok = $verifier->verify($canal, (string) $request->query('email'));

        return redirect()->away(
            rtrim((string) config('app.frontend_url'), '/').'/organizatori/'.$canal->id.'?kontakt='.($ok ? 'overeny' : 'neplatny'),
        );
    }

    public function show($id, Request $request, ViewRecorder $viewRecorder)
    {
        $canal = $this->canalRepository->publicShow($id);

        if (! $canal) {
            abort(404);
        }

        $viewRecorder->record($canal, $request);

        $data = $canal->toArray();
        // Kontaktovateľné len ak má kanál aktívneho majiteľa (self/admin
        // registrácia, overený e-mail) a návštevník ním nie je sám.
        $data['contactable'] = $canal->isContactableBy(auth('sanctum')->user());

        // „Spravujete tento kanál?" — len pri kanáli, ktorý nikto nespravuje
        // a nie je zberný. `claim_contact` = dá sa overiť cez kontaktnú adresu.
        $claims = app(CanalClaims::class);
        $data['claimable'] = $claims->isClaimable($canal);
        $data['claim_contact'] = $data['claimable'] && $claims->contactOf($canal) !== null;
        // Kontaktnú adresu a stav správy UI nepotrebuje — nesú ich tieto príznaky.
        unset($data['claimed_by_user_id']);

        // Obec a (publikované) miesta pre verejný detail – rovnaký tvar ako
        // v CanalResource, aby ich front vedel zobraziť.
        if ($canal->relationLoaded('municipality') && $canal->municipality) {
            $data['municipality'] = [
                'id' => $canal->municipality->id,
                'name' => $canal->municipality->fullname,
            ];
        }

        if ($canal->relationLoaded('venues')) {
            $data['venues_list'] = $canal->venues->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'is_owner' => (bool) $v->pivot->is_owner,
            ])->values()->all();
        }

        return response()->json($data);
    }

    /**
     * Publikované eventy organizované týmto kanálom (verejný zoznam).
     */
    public function events($id): JsonResponse
    {
        $canal = Canal::findOrFail($id);

        // Aj archivované — po skončení podujatia ho tam preklopí
        // `app:events-archive-finished`, takže filter len na `published` by
        // z profilu organizátora spravil zoznam bez histórie. Zoradenie je
        // zostupné, takže nadchádzajúce ostávajú hore.
        $events = Event::where('canal_id', $canal->id)
            ->whereIn('status', ModelStatus::publiclyReadableValues())
            ->with('canal')
            // Explicitný select, nie get([...]) — withExists by si inak
            // vynútil `events.*`. Uzávierku registrácie číta ticketCta().
            ->select(['id', 'name', 'slug', 'start_at', 'end_at', 'registration_deadline_at', 'status', 'canal_id'])
            ->withTicketCtaFlags()
            ->orderByDesc('start_at')
            ->limit(100)
            ->get();

        return response()->json($events->map(fn ($ev) => [
            'id' => $ev->id,
            'name' => $ev->name,
            // Slug ide von, aby karta odkazovala na kanonickú adresu
            // `/podujatia/{slug}-{id}` a nie na holé číslo.
            'slug' => $ev->slug,
            'start_at' => $ev->start_at,
            'end_at' => $ev->end_at,
            'status' => $ev->status,
            'image_url' => $ev->thumb_image,
            // Karta si z dvojice thumb/large poskladá srcset — na retina displeji
            // je 320px thumb na 160px vysokej karte viditeľne rozmazaný.
            'image_url_large' => $ev->primary_image['large'],
            // Tlačidlo lístkov na karte — viď Event::ticketCta().
            'ticket_cta' => $ev->ticketCta(),
        ]));
    }
}
