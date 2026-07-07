<?php

use App\Enums\AdmissionStatus;
use App\Enums\TicketStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Backfill: create one default ticket type per ticketed event and split every
     * existing ticket (order) into individual admissions (one per seat / quantity).
     */
    public function up(): void
    {
        $now = now();

        // 1) One default ticket type per event that already has tickets.
        $eventIds = DB::table('tickets')->distinct()->pluck('event_id');
        $typeByEvent = [];

        foreach ($eventIds as $eventId) {
            $event = DB::table('events')->where('id', $eventId)->first();

            $typeByEvent[$eventId] = DB::table('ticket_types')->insertGetId([
                'event_id' => $eventId,
                'name' => 'Vstupenka',
                'description' => null,
                'price_amount' => $event->price_amount ?? null,
                'price_currency' => $event->price_currency ?? 'EUR',
                'capacity' => $event->capacity ?? null,
                'max_per_order' => 10,
                'min_per_order' => 1,
                'requires_attendee_name' => false,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 2) Split every ticket into admissions. Carry the original qr_token /
        //    check-in onto the FIRST admission; generate fresh tokens for the rest.
        DB::table('tickets')->orderBy('id')->chunkById(200, function ($tickets) use ($typeByEvent, $now) {
            $rows = [];

            foreach ($tickets as $ticket) {
                $quantity = max(1, (int) ($ticket->quantity ?? 1));
                $status = $ticket->status === TicketStatus::Cancelled->value
                    ? AdmissionStatus::Cancelled->value
                    : AdmissionStatus::Valid->value;

                for ($i = 0; $i < $quantity; $i++) {
                    $rows[] = [
                        'uuid' => (string) Str::uuid(),
                        'ticket_id' => $ticket->id,
                        'ticket_type_id' => $typeByEvent[$ticket->event_id] ?? null,
                        'event_id' => $ticket->event_id,
                        'attendee_name' => $ticket->holder_name,
                        'qr_token' => $i === 0 ? $ticket->qr_token : Str::random(64),
                        'status' => $status,
                        'checked_in_at' => $i === 0 ? $ticket->checked_in_at : null,
                        'checked_in_by' => $i === 0 ? $ticket->checked_in_by : null,
                        'meta' => null,
                        'deleted_at' => $ticket->deleted_at,
                        'created_at' => $ticket->created_at ?? $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($rows !== []) {
                DB::table('ticket_admissions')->insert($rows);
            }
        });
    }

    /**
     * Reverse: wipe generated admissions and default ticket types.
     */
    public function down(): void
    {
        DB::table('ticket_admissions')->delete();
        DB::table('ticket_types')->delete();
    }
};
