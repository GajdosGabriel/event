<?php

namespace Tests\Feature\Canal;

use App\Models\Canal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\CanalSetupTest;

/**
 * Úprava kanála z dashboardu.
 *
 * Rozhoduje per-kanálová rola, nie globálne právo: vlastník svoj kanál
 * upraviť smie (PersonalCanalProvisioner mu osobný kanál pripája ako
 * CanalRole::Owner), cudzí kanál sa mu do dashboardového scope vôbec
 * nedostane a skončí na 404.
 */
class DashboardCanalUpdateTest extends CanalSetupTest
{
    private string $bodySuffix = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Kanál si po uložení dopĺňa súradnice z adresy — bez fake volania
        // čaká test na Nominatim aj 50 sekúnd.
        Http::fake(['*' => Http::response([])]);
    }

    #[Test]
    public function owner_can_update_own_canal_from_dashboard_scope(): void
    {
        $canal = $this->user->canals->first();
        $payload = $this->payload();

        $response = $this->putJson("/api/dashboard/canals/{$canal->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('canals', [
            'id' => $canal->id,
            'name' => $payload['name'],
        ]);

        // Telo prechádza sanitizérom (surový text sa zabalí do <p>), takže sa
        // porovnáva obsah, nie presný tvar HTML.
        $this->assertStringContainsString($this->bodySuffix, (string) $canal->fresh()?->body);
    }

    /**
     * Zmazaný kanál sa editovať smie a zmazaný zostáva — rovnaká logika ako
     * pri podujatiach (DashboardEventUpdateTest). Dashboard ich vypisuje
     * `withTrashed()`, aby sa dali obnoviť aj opraviť.
     */
    #[Test]
    public function soft_deleted_canal_can_still_be_updated_and_stays_deleted(): void
    {
        $canal = $this->user->canals->first();
        $canal->delete();

        $payload = $this->payload();

        $response = $this->putJson("/api/dashboard/canals/{$canal->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('canals', [
            'id' => $canal->id,
            'name' => $payload['name'],
        ]);

        // Telo prechádza sanitizérom (surový text sa zabalí do <p>), takže sa
        // porovnáva obsah, nie presný tvar HTML.
        $this->assertStringContainsString($this->bodySuffix, (string) $canal->fresh()?->body);

        $this->assertSoftDeleted('canals', [
            'id' => $canal->id,
        ]);
    }

    #[Test]
    public function foreign_canal_cannot_be_updated_from_dashboard_scope(): void
    {
        $foreign = Canal::factory()->create();
        $payload = $this->payload();

        $response = $this->putJson("/api/dashboard/canals/{$foreign->id}", $payload);

        $response->assertStatus(404);

        $this->assertDatabaseMissing('canals', [
            'id' => $foreign->id,
            'name' => $payload['name'],
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $this->bodySuffix = Str::random(30);

        return array_merge($this->formCanal, [
            'name' => $this->formCanal['name'].Str::random(5),
            'body' => $this->formCanal['body'].$this->bodySuffix,
            'published_at' => now(),
        ]);
    }
}
