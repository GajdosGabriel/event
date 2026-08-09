<?php

namespace Tests\Feature\Attributes;

use App\Enums\ModelStatus;
use App\Models\Canal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Beacon z verejnej stránky: „niekto klikol na tento odkaz, over ho".
 *
 * Podstatné je to, čo endpoint **neprijíma** — žiadnu adresu. Keby ju bral,
 * dal by sa cez neho poslať náš server kamkoľvek (SSRF) a ešte aj vložiť
 * cudzí odkaz do e-mailu majiteľovi.
 */
class BrokenLinkReportTest extends TestCase
{
    use RefreshDatabase;

    private function canal(): Canal
    {
        return Canal::factory()->create([
            'status' => ModelStatus::Published->value,
            'municipality_id' => 1,
            'website' => 'https://divadlo.sk',
        ]);
    }

    #[Test]
    public function a_click_marks_the_link_for_immediate_checking(): void
    {
        $canal = $this->canal();

        $this->postJson('/api/link-reports', [
            'type' => 'canal',
            'id' => $canal->id,
            'attribute' => 'website',
            'from' => '/organizatori/divadlo-1',
        ])->assertStatus(202);

        $check = $canal->attributeChecks()->first();

        $this->assertSame('/organizatori/divadlo-1', $check->reported_from);
        $this->assertNotNull($check->reported_at);
        $this->assertTrue($check->next_check_at->isPast() || $check->next_check_at->isCurrentMinute());
    }

    #[Test]
    public function a_foreign_address_never_gets_stored(): void
    {
        $canal = $this->canal();

        foreach (['https://zly.sk/phishing', '//zly.sk', 'organizatori/x'] as $from) {
            $canal->attributeChecks()->update(['reported_at' => null, 'reported_from' => null]);

            $this->postJson('/api/link-reports', [
                'type' => 'canal',
                'id' => $canal->id,
                'from' => $from,
            ])->assertStatus(202);

            // Hodnota ide do e-mailu majiteľovi. Čokoľvek, čo nie je cesta na
            // našom webe, sa zahodí — inak by upozornenie roznášalo cudzie odkazy.
            $this->assertNull($canal->attributeChecks()->first()->reported_from, $from);
        }
    }

    #[Test]
    public function the_query_string_is_dropped(): void
    {
        $canal = $this->canal();

        $this->postJson('/api/link-reports', [
            'type' => 'canal',
            'id' => $canal->id,
            'from' => '/organizatori/divadlo-1?utm_source=mail&email=niekto@niekde.sk',
        ])->assertStatus(202);

        // Do adresy stránky nepatria osobné údaje a v e-maile ich netreba.
        $this->assertSame('/organizatori/divadlo-1', $canal->attributeChecks()->first()->reported_from);
    }

    #[Test]
    public function an_unknown_record_is_answered_the_same_way(): void
    {
        // Odpoveď nesmie prezradiť, či id existuje — inak by z endpointu bol
        // nástroj na zisťovanie, čo všetko v databáze je.
        $this->postJson('/api/link-reports', ['type' => 'canal', 'id' => 999999])
            ->assertStatus(202);
    }

    #[Test]
    public function unknown_types_and_attributes_are_rejected(): void
    {
        $canal = $this->canal();

        $this->postJson('/api/link-reports', ['type' => 'user', 'id' => 1])
            ->assertStatus(422);

        $this->postJson('/api/link-reports', ['type' => 'canal', 'id' => $canal->id, 'attribute' => 'email'])
            ->assertStatus(422);
    }
}
