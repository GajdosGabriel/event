<?php

namespace Tests\Feature\Events;

use App\Enums\AdmissionStatus;
use App\Enums\ModelStatus;
use App\Enums\TicketStatus;
use App\Enums\TicketTypeKind;
use App\Models\Admission;
use App\Models\Ticket;
use App\Models\TicketType;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Prehratie offline fronty skenera.
 *
 * Skener pri výpadku signálu skeny ukladá a odošle ich neskôr. Server na to
 * potrebuje dve vlastnosti: byť idempotentný (aby druhé odoslanie neškodilo)
 * a prijať čas skenu (aby všetci, čo prišli počas výpadku, nemali zapísaný
 * jeden a ten istý okamih).
 */
class CheckinOfflineQueueTest extends EventSetupTest
{
    #[Test]
    public function replayed_scan_records_the_time_it_was_scanned(): void
    {
        $admission = $this->admission();
        $scannedAt = Carbon::now()->subMinutes(40);

        $response = $this->postJson('/api/dashboard/tickets/checkin', [
            'qr_token' => $admission->qr_token,
            'scanned_at' => $scannedAt->toIso8601String(),
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'checked_in');

        $this->assertTrue(
            $scannedAt->equalTo($admission->fresh()->checked_in_at),
            'Prehratý sken musí niesť čas príchodu, nie čas, keď sa vrátil signál.',
        );
    }

    /** Bez času skenu ostáva pôvodné správanie — zapíše sa „teraz". */
    #[Test]
    public function online_scan_still_uses_the_server_time(): void
    {
        $admission = $this->admission();

        $this->postJson('/api/dashboard/tickets/checkin', [
            'qr_token' => $admission->qr_token,
        ])->assertOk();

        $this->assertTrue($admission->fresh()->checked_in_at->isAfter(Carbon::now()->subMinute()));
    }

    /**
     * Na tom stojí celá fronta: záznam sa z nej maže až po odpovedi servera,
     * takže sa sken môže poslať dvakrát. Druhýkrát nesmie nič prepísať.
     */
    #[Test]
    public function sending_the_same_scan_twice_changes_nothing(): void
    {
        $admission = $this->admission();
        $scannedAt = Carbon::now()->subMinutes(30);

        $payload = [
            'qr_token' => $admission->qr_token,
            'scanned_at' => $scannedAt->toIso8601String(),
        ];

        $this->postJson('/api/dashboard/tickets/checkin', $payload)->assertJsonPath('status', 'checked_in');

        $second = $this->postJson('/api/dashboard/tickets/checkin', $payload);

        $second->assertOk();
        $second->assertJsonPath('status', 'already_checked_in');

        $this->assertTrue($scannedAt->equalTo($admission->fresh()->checked_in_at));
    }

    /** Čas z budúcnosti ani spred týždňa nie je sken — je to preklep alebo pokus. */
    #[Test]
    public function implausible_scan_time_is_rejected(): void
    {
        $admission = $this->admission();

        $this->postJson('/api/dashboard/tickets/checkin', [
            'qr_token' => $admission->qr_token,
            'scanned_at' => Carbon::now()->addHour()->toIso8601String(),
        ])->assertJsonValidationErrors('scanned_at');

        $this->postJson('/api/dashboard/tickets/checkin', [
            'qr_token' => $admission->qr_token,
            'scanned_at' => Carbon::now()->subWeek()->toIso8601String(),
        ])->assertJsonValidationErrors('scanned_at');

        $this->assertNull($admission->fresh()->checked_in_at);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Skenovať smie len ten, kto má právo na vstup — v základnom fixture
        // ho používateľ nemá, lebo väčšina testov nad podujatiami ho nerieši.
        $this->user->givePermissionTo('ticket.checkin');
    }

    private function admission(): Admission
    {
        $this->futureEvent->update(['status' => ModelStatus::Published->value]);

        $type = TicketType::create([
            'event_id' => $this->futureEvent->id,
            'name' => 'Vstupenka',
            'kind' => TicketTypeKind::Ticket->value,
            'capacity' => 100,
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $this->futureEvent->id,
            'user_id' => $this->user->id,
            'holder_name' => 'Janko Hosť',
            'holder_email' => 'janko@example.test',
            'status' => TicketStatus::Confirmed->value,
        ]);

        return Admission::create([
            'uuid' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'event_id' => $this->futureEvent->id,
            'ticket_type_id' => $type->id,
            'qr_token' => Str::random(40),
            'status' => AdmissionStatus::Valid->value,
        ]);
    }
}
