<?php

namespace Tests\Feature\Imports;

use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompleteNewestCanalsVenuesEventsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_22_100000_complete_newest_canals_venues_events.php';

    #[Test]
    public function it_completes_imported_canal_and_cleans_ecav_events(): void
    {
        $mnisek = $this->municipality('mnisek-nad-hnilcom', 'Mníšek nad Hnilcom');
        $gelnica = $this->municipality('gelnica', 'Gelnica');

        $canal = Canal::factory()->create([
            'id' => 1969,
            'name' => 'CZ ECAV Gelnica',
            'body' => '<p>CZ ECAV Gelnica je cirkevný zbor.</p>',
            'phone' => null,
            'website' => null,
            'street' => null,
            'municipality_id' => $mnisek,
            'coordinates_source' => 'municipality',
            'registration_source' => RegistrationSource::IMPORT->value,
            'claimed_at' => null,
        ]);
        $claimed = Canal::factory()->create([
            'id' => 1968,
            'name' => 'CZ ECAV Rožňava',
            'body' => '<p>Vlastný popis.</p>',
            'claimed_at' => now(),
        ]);

        $user = User::factory()->create();
        $noisy = Event::factory()->create([
            'canal_id' => $canal->id, 'user_id' => $user->id,
            'orginal_source' => 'https://www.ecav.sk/zborovy-den',
            'body' => '<ul><li>Mesto: Gelnica</li><li>Ulica</li><li><strong>Webstránka:</strong></li>'
                .'<li><strong>Webstránka:</strong> https://www.ecavpoltar.sk</li></ul>'
                .'<p><strong>Mesto:</strong> Ľubietová<br><strong>Ulica:</strong><br><strong>Webstránka:</strong></p>',
        ]);

        (require database_path(self::MIGRATION))->up();

        $canal->refresh();
        $this->assertSame(3, substr_count($canal->body, '<p>'));
        $this->assertSame('0945 506 078', $canal->phone);
        $this->assertSame($gelnica, (int) $canal->municipality_id);
        $this->assertSame('Lutherovo námestie 5', $canal->street);
        $this->assertSame('address', $canal->coordinates_source);
        $this->assertContains('gelnica@ecav.sk', $canal->emails()->pluck('email')->all());

        $this->assertSame('<p>Vlastný popis.</p>', $claimed->fresh()->body);

        $this->assertSame(
            '<ul><li>Mesto: Gelnica</li><li><strong>Webstránka:</strong> https://www.ecavpoltar.sk</li></ul>'
                .'<p><strong>Mesto:</strong> Ľubietová</p>',
            preg_replace('/>\s+</', '><', $noisy->fresh()->body),
        );
    }

    private function municipality(string $slug, string $name): int
    {
        return (int) (DB::table('municipalities')->where('slug', $slug)->value('id')
            ?? DB::table('municipalities')->insertGetId([
                'fullname' => $name, 'shortname' => $name, 'slug' => $slug, 'zip' => '',
                'district_id' => 0, 'region_id' => 0, 'use' => true,
            ]));
    }
}
