<?php

namespace Tests\Feature\Imports;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Services\Imports\EventSourceLookup;
use App\Services\Imports\ImportedEventMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportedEventMatcherTest extends TestCase
{
    use RefreshDatabase;

    private const BODY = 'Spolok svätého Vojtecha pozýva na prezentáciu knihy Prebúdzanie milosrdenstva v Dome Quo Vadis v Bratislave. '
        .'Diskusia o posolstve Ježišovho Srdca s autorom, kňazom a teológom, bude nasledovať po krátkom úvodnom slove vydavateľa. '
        .'Vstup je voľný, knihu si návštevníci budú môcť zakúpiť priamo na mieste za zvýhodnenú cenu.';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.imports.dedupe_with_ai' => false]);
        $this->municipality(242, 'bratislava', 'Bratislava');
    }

    #[Test]
    public function it_finds_the_same_event_from_another_article(): void
    {
        $venue = $this->venue('Dom Quo Vadis');
        $start = Carbon::parse('2026-11-26 17:00:00');
        $existing = $this->event('Prebúdzanie milosrdenstva', self::BODY, $start, $venue);

        $found = app(ImportedEventMatcher::class)->find(
            'Prebúdzanie milosrdenstva: Diskusia o posolstve Ježišovho Srdca',
            'Spolok svätého Vojtecha pozýva na diskusiu o knihe Prebúdzanie milosrdenstva v Dome Quo Vadis v Bratislave. '
                .'Diskusia o posolstve Ježišovho Srdca s autorom, kňazom a teológom. Vstup je voľný, knihu si návštevníci budú môcť zakúpiť na mieste.',
            $start,
            $venue->id,
        );

        $this->assertSame($existing->id, $found?->id);
    }

    #[Test]
    public function it_keeps_different_courses_of_one_organizer_apart(): void
    {
        $venue = $this->venue('Kláštor kapucínov (Pezinok)');
        $start = Carbon::parse('2026-09-27 17:00:00');
        $this->event('Kríza republiky: Lekcie zo starovekého Ríma | online kurz', 'Ako sa rozpadla rímska republika? Historik sprevádza poslucháčov obdobím Gracchovcov, Mária, Sullu, Pompeia a Caesara, číta Cicerónove listy a reči a hľadá paralely so súčasnými demokraciami. Kolégium ponúka online kurz, prihlásiť sa dá cez formulár.', $start, $venue);

        $found = app(ImportedEventMatcher::class)->find(
            'Lev XIV., Boh a Cirkev dnes | ONLINE kurz',
            'Prvý americký pápež, augustinián Robert Prevost, a jeho program: encyklika Magnifica humanitas, synodalita, umelá inteligencia, mier na Ukrajine a vzťah Cirkvi k modernému svetu. Teológ predstaví prvé mesiace pontifikátu. Kolégium ponúka online kurz, prihlásiť sa dá cez formulár.',
            $start,
            $venue->id,
        );

        $this->assertNull($found);
    }

    #[Test]
    public function it_ignores_venues_named_only_after_the_town_and_other_times(): void
    {
        $town = $this->venue('Bratislava');
        $start = Carbon::parse('2026-09-19 22:00:00');
        $this->event('Národný pochod za život', self::BODY, $start, $town);

        $this->assertNull(app(ImportedEventMatcher::class)->find('Národný pochod za život', self::BODY, $start, $town->id));

        $venue = $this->venue('Dom Quo Vadis');
        $this->event('Prebúdzanie milosrdenstva', self::BODY, $start, $venue);

        $this->assertNull(app(ImportedEventMatcher::class)->find('Prebúdzanie milosrdenstva', self::BODY, $start->copy()->addHour(), $venue->id));
    }

    #[Test]
    public function the_remembered_source_finds_the_event_next_time(): void
    {
        $event = $this->event('Prebúdzanie milosrdenstva', self::BODY, Carbon::parse('2026-11-26 17:00:00'), $this->venue('Dom Quo Vadis'));

        ImportedEventMatcher::rememberSource($event, 'https://www.vyveska.sk/prebudzanie-milosrdenstva/');
        ImportedEventMatcher::rememberSource($event, 'https://www.vyveska.sk/prebudzanie-milosrdenstva/');

        $this->assertSame(['https://www.vyveska.sk/prebudzanie-milosrdenstva/'], $event->fresh()->meta['merged_sources']);
        $this->assertSame($event->id, EventSourceLookup::find('https://www.vyveska.sk/prebudzanie-milosrdenstva/')?->id);
    }

    private function venue(string $name): Venue
    {
        return Venue::factory()->create(['name' => $name, 'village_id' => 242, 'status' => ModelStatus::Published, 'category' => null]);
    }

    private function event(string $name, string $body, Carbon $start, Venue $venue): Event
    {
        return Event::factory()->create([
            'name' => $name, 'body' => '<p>'.$body.'</p>', 'start_at' => $start, 'end_at' => $start->copy()->addHours(2),
            'venue_id' => $venue->id, 'canal_id' => Canal::factory()->create()->id, 'user_id' => User::factory()->create()->id,
            'orginal_source' => 'https://www.tkkbs.sk/view.php?cisloclanku='.random_int(1000, 9999),
        ]);
    }

    private function municipality(int $id, string $slug, string $name): void
    {
        if (! DB::table('municipalities')->where('id', $id)->exists()) {
            DB::table('municipalities')->insert([
                'id' => $id, 'fullname' => $name, 'shortname' => $name, 'slug' => $slug, 'zip' => '',
                'district_id' => 0, 'region_id' => 0, 'use' => true,
            ]);
        }
    }
}
