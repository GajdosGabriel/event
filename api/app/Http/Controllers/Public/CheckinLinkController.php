<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\CheckinLink;
use App\Repositories\Contracts\TicketRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Skener pre dobrovoľníkov pri vchode — `/vstup/{token}`, bez prihlásenia.
 *
 * Token odkazu je autorizácia (ako `/q/{token}`). Odkaz vie len tri veci:
 * ukázať, ku ktorému podujatiu patrí, overiť vstupenku tohto podujatia a
 * povedať počty príchodov. Zoznam prihlásených, e-maily ani telefóny cez neho
 * neodídu — vo výsledku skenu je len meno na vstupenke a typ lístka, ktoré
 * obsluha pri dverách potrebuje.
 *
 * Neplatný, zrušený aj expirovaný odkaz vracia rovnakú 404: držiteľ starého
 * odkazu sa nemá čo dozvedieť o tom, prečo už nefunguje.
 */
class CheckinLinkController extends Controller
{
    public function __construct(private readonly TicketRepository $tickets) {}

    public function show(string $token): JsonResponse
    {
        $link = $this->activeOrFail($token);
        $event = $link->event()->with('venue:id,name')->firstOrFail();

        return response()->json([
            'label' => $link->label,
            'expires_at' => $link->expires_at,
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'start_at' => $event->start_at,
                'end_at' => $event->end_at,
                'venue' => $event->venue?->name,
            ],
            'has_types' => $event->ticketTypes()->exists(),
            'stats' => $this->tickets->checkinStatsViaLink($link),
        ]);
    }

    public function scan(Request $request, string $token): JsonResponse
    {
        $link = $this->activeOrFail($token);

        // Rovnaké pravidlá ako TicketCheckinRequest — čas skenu z offline fronty.
        $data = $request->validate([
            'qr_token' => ['required', 'string', 'max:255'],
            'scanned_at' => ['nullable', 'date', 'before_or_equal:now', 'after:-1 day'],
        ]);

        $result = $this->tickets->checkInViaLink(
            $link,
            $data['qr_token'],
            isset($data['scanned_at']) ? Carbon::parse($data['scanned_at']) : null,
        );

        /** @var Admission|null $admission */
        $admission = $result['admission'] ?? null;

        return response()->json([
            'status' => $result['status'],
            'reason' => $result['reason'] ?? null,
            'payment_due' => $admission?->ticket?->awaitsPayment() ? [
                'amount' => (int) $admission->ticket->price_amount,
                'currency' => $admission->ticket->price_currency ?? 'EUR',
                'status_label' => $admission->ticket->payment_status->label(),
            ] : null,
            'admission' => $admission ? $this->admission($admission) : null,
        ]);
    }

    public function stats(string $token): JsonResponse
    {
        return response()->json($this->tickets->checkinStatsViaLink($this->activeOrFail($token)));
    }

    private function activeOrFail(string $token): CheckinLink
    {
        $link = CheckinLink::query()->active()->where('token', $token)->first();

        abort_if($link === null, 404);

        return $link;
    }

    /**
     * Len to, čo potrebuje človek pri dverách. Zámerne nie AdmissionResource:
     * ten nesie aj odkaz na QR kód, odpovede dotazníka a ďalšie údaje
     * organizátora, ktoré dobrovoľníkovi bez účtu nepatria.
     *
     * @return array<string, mixed>
     */
    private function admission(Admission $admission): array
    {
        $admission->loadMissing(['ticket', 'ticketType', 'checkedInBy', 'checkedInLink']);

        return [
            'id' => $admission->id,
            'attendee_name' => $admission->attendee_name,
            'holder_name' => $admission->ticket?->holder_name,
            'status' => $admission->status,
            'is_checked_in' => $admission->is_checked_in,
            'checked_in_at' => $admission->checked_in_at,
            // Kto pustil skôr: člen tímu (bez mena — dobrovoľníkovi stačí
            // „obsluha"), alebo ktorý odkaz.
            'checked_in_by' => $admission->checkedInBy ? ['id' => null, 'name' => null] : null,
            'checked_in_link' => $admission->checkedInLink
                ? ['id' => $admission->checkedInLink->id, 'label' => $admission->checkedInLink->label]
                : null,
            'ticket_type' => $admission->ticketType ? [
                'id' => $admission->ticketType->id,
                'name' => $admission->ticketType->name,
                'kind' => $admission->ticketType->kind,
            ] : null,
        ];
    }
}
