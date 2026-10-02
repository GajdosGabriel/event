<?php

namespace App\Http\Resources;

use App\Models\Admission;
use App\Services\Tickets\TicketOwnership;
use Illuminate\Http\Request;

/**
 * Objednávka tak, ako ju vidí prihlásený účet v „Mojich lístkoch".
 *
 * Objednávateľ dostane celú objednávku. Účastník, ktorého na objednávku
 * zapísal niekto iný, smie vidieť len svoje vstupenky: `uuid` objednávky sa mu
 * neposiela, lebo `/tickets/{uuid}` ukazuje QR kódy všetkých miest.
 */
class MyTicketResource extends TicketResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $ownership = app(TicketOwnership::class);
        $user = $request->user();

        if ($ownership->isHolder($this->resource, $user)) {
            return [...$data, 'attendee_only' => false];
        }

        $email = $ownership->email($user);
        $mine = $this->admissions
            ->filter(fn (Admission $admission) => mb_strtolower(trim((string) $admission->attendee_email)) === $email)
            ->values();

        return [
            ...$data,
            'uuid' => $mine->first()?->uuid,
            'attendee_only' => true,
            'quantity' => $mine->count(),
            'price_amount' => null,
            'checked_in_count' => $mine->filter(fn (Admission $admission) => $admission->is_checked_in)->count(),
            'admissions_total' => $mine->count(),
            'admissions' => AdmissionResource::collection($mine),
            // Stránka /rsvp/{token}: potvrdenie, odmietnutie alebo zrušenie účasti.
            'rsvp_token' => $mine->first(fn (Admission $admission) => $admission->confirmation_token !== null)?->confirmation_token,
        ];
    }
}
