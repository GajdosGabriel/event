<?php

namespace Tests\Feature\Imports;

use App\Models\Canal;
use App\Models\DuplicateDecision;
use App\Models\Municipality;
use App\Models\Venue;
use App\Services\Imports\DuplicateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DuplicateResolverTest extends TestCase
{
    use RefreshDatabase;

    private function fakeJudge(bool $same, float $confidence, string $reason = 'Test.'): void
    {
        config()->set('openai.api_key', 'test-key');
        config()->set('services.imports.dedupe_with_ai', true);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'test',
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['content' => json_encode([
                        'same' => $same,
                        'confidence' => $confidence,
                        'reason' => $reason,
                    ])],
                ]],
            ]),
        ]);
    }

    #[Test]
    public function a_confident_ai_verdict_reuses_the_existing_canal(): void
    {
        $this->fakeJudge(true, 0.95, 'Rovnaká farnosť s iným zápisom názvu.');

        $existing = Canal::factory()->create(['name' => 'Farnosť Nitra – Kalvária']);

        $found = app(DuplicateResolver::class)->findCanal('Kalvária Nitra farnosť');

        $this->assertNotNull($found);
        $this->assertSame($existing->id, $found->id);
        $this->assertDatabaseHas('duplicate_decisions', [
            'entity' => 'canal',
            'candidate_id' => $existing->id,
            'decision' => DuplicateDecision::SAME,
            'source' => DuplicateDecision::SOURCE_AI,
        ]);
    }

    #[Test]
    public function an_unsure_verdict_creates_nothing_but_queues_the_record_for_review(): void
    {
        $this->fakeJudge(true, 0.6);

        $existing = Canal::factory()->create(['name' => 'Farnosť Nitra – Kalvária']);
        $resolver = app(DuplicateResolver::class);

        $this->assertNull($resolver->findCanal('Kalvária Nitra farnosť'));

        $created = Canal::factory()->create(['name' => 'Kalvária Nitra farnosť']);
        $resolver->markCreated($created);

        $this->assertDatabaseHas('duplicate_decisions', [
            'candidate_id' => $existing->id,
            'created_id' => $created->id,
            'decision' => DuplicateDecision::UNCERTAIN,
            'reviewed_at' => null,
        ]);
    }

    #[Test]
    public function a_different_verdict_is_remembered_and_not_asked_twice(): void
    {
        $this->fakeJudge(false, 0.9);

        Canal::factory()->create(['name' => 'Farnosť Nitra – Kalvária']);
        $resolver = app(DuplicateResolver::class);

        $this->assertNull($resolver->findCanal('Kalvária Nitra farnosť'));
        $this->assertNull($resolver->findCanal('Kalvária Nitra farnosť'));

        Http::assertSentCount(1);
        $this->assertDatabaseHas('duplicate_decisions', ['decision' => DuplicateDecision::DISTINCT]);
    }

    #[Test]
    public function without_ai_a_similar_name_is_only_logged_never_merged(): void
    {
        config()->set('services.imports.dedupe_with_ai', false);
        Http::fake();

        $existing = Canal::factory()->create(['name' => 'Farnosť Nitra – Kalvária']);

        $this->assertNull(app(DuplicateResolver::class)->findCanal('Kalvária Nitra farnosť'));

        Http::assertNothingSent();
        $this->assertDatabaseHas('duplicate_decisions', [
            'candidate_id' => $existing->id,
            'decision' => DuplicateDecision::UNCERTAIN,
            'source' => DuplicateDecision::SOURCE_RULE,
        ]);
    }

    #[Test]
    public function an_unrelated_name_is_never_a_candidate(): void
    {
        config()->set('services.imports.dedupe_with_ai', true);
        Http::fake();

        Canal::factory()->create(['name' => 'Farnosť Nitra – Kalvária']);

        $this->assertNull(app(DuplicateResolver::class)->findCanal('Spoločenstvo evanjelických žien'));

        Http::assertNothingSent();
        $this->assertDatabaseCount('duplicate_decisions', 0);
    }

    #[Test]
    public function the_same_organizer_written_differently_merges_without_ai(): void
    {
        config()->set('services.imports.dedupe_with_ai', false);
        Http::fake();

        $existing = Canal::factory()->create(['name' => 'Musica aeterna o.z']);
        // Staršie „nerozhodnuté" rozhodnutie nesmie istú zhodu prebiť.
        DuplicateDecision::query()->create([
            'entity' => DuplicateDecision::ENTITY_CANAL, 'input_key' => 'musica-aeterna', 'input_name' => 'Musica aeterna',
            'candidate_id' => $existing->id, 'decision' => DuplicateDecision::UNCERTAIN, 'source' => DuplicateDecision::SOURCE_RULE,
        ]);

        $found = app(DuplicateResolver::class)->findCanal('Musica aeterna');

        $this->assertSame($existing->id, $found?->id);
        Http::assertNothingSent();
        $this->assertDatabaseHas('duplicate_decisions', ['candidate_id' => $existing->id, 'decision' => DuplicateDecision::SAME]);
    }

    #[Test]
    public function the_name_of_a_merged_canal_leads_to_the_canal_it_was_merged_into(): void
    {
        config()->set('services.imports.dedupe_with_ai', false);
        Http::fake();

        $target = Canal::factory()->create(['name' => 'Televízia LUX']);
        $middle = Canal::factory()->create(['name' => 'Slovenská katolícka Televízia LUX']);
        $alias = Canal::factory()->create(['name' => 'TV LUX']);
        $alias->forceFill(['merged_into_id' => $middle->id])->save();
        $alias->delete();
        $middle->forceFill(['merged_into_id' => $target->id])->save();
        $middle->delete();

        $this->assertSame($target->id, app(DuplicateResolver::class)->findCanal('TV LUX')?->id);
    }

    #[Test]
    public function a_unique_website_with_a_similar_name_merges_without_ai(): void
    {
        config()->set('services.imports.dedupe_with_ai', true);
        Http::fake();

        $existing = Canal::factory()->create([
            'name' => 'Misijná škola Karola Wojtylu',
            'website' => 'https://www.mskw.sk',
        ]);

        $found = app(DuplicateResolver::class)->findCanal('Misijná škola K. Wojtylu', 'https://mskw.sk/o-nas');

        $this->assertSame($existing->id, $found?->id);
        Http::assertNothingSent();
        $this->assertDatabaseHas('duplicate_decisions', ['source' => DuplicateDecision::SOURCE_RULE, 'decision' => DuplicateDecision::SAME]);
    }

    #[Test]
    public function a_website_shared_by_many_canals_proves_nothing(): void
    {
        config()->set('services.imports.dedupe_with_ai', false);
        Http::fake();

        // Web zdroja nesie každý importovaný kanál z toho scrapera.
        Canal::factory()->create(['name' => 'SEŽ ECAV', 'website' => 'https://www.ecav.sk']);
        Canal::factory()->create(['name' => 'Tranoscius', 'website' => 'https://www.ecav.sk']);

        $this->assertNull(app(DuplicateResolver::class)->findCanal('SEŽ ECAV Bratislava', 'https://www.ecav.sk'));
        $this->assertDatabaseMissing('duplicate_decisions', ['decision' => DuplicateDecision::SAME]);
    }

    #[Test]
    public function a_venue_is_only_compared_inside_its_own_town(): void
    {
        $this->fakeJudge(true, 0.95);

        $kosice = Municipality::query()->where('slug', 'kosice')->firstOrFail();
        $trnava = Municipality::query()->where('slug', 'trnava')->firstOrFail();

        Venue::factory()->create([
            'name' => 'Rímskokatolícky kostol sv. Jozefa',
            'category' => null,
            'village_id' => $kosice->id,
        ]);

        $this->assertNull(app(DuplicateResolver::class)->findVenue('Kostol sv. Jozefa', $trnava->id));

        Http::assertNothingSent();
    }

    #[Test]
    public function a_venue_in_the_same_town_with_a_confident_verdict_is_reused(): void
    {
        $this->fakeJudge(true, 0.92);

        $kosice = Municipality::query()->where('slug', 'kosice')->firstOrFail();

        $existing = Venue::factory()->create([
            'name' => 'Rímskokatolícky kostol sv. Jozefa',
            'category' => null,
            'village_id' => $kosice->id,
        ]);

        $found = app(DuplicateResolver::class)->findVenue('Kostol sv. Jozefa', $kosice->id);

        $this->assertSame($existing->id, $found?->id);
    }

    #[Test]
    public function different_saints_in_the_same_town_are_not_candidates(): void
    {
        $this->fakeJudge(true, 0.99);

        $kosice = Municipality::query()->where('slug', 'kosice')->firstOrFail();

        Venue::factory()->create([
            'name' => 'Kostol sv. Petra',
            'category' => null,
            'village_id' => $kosice->id,
        ]);

        $this->assertNull(app(DuplicateResolver::class)->findVenue('Kostol sv. Jozefa', $kosice->id));

        Http::assertNothingSent();
    }

    #[Test]
    public function a_venue_given_only_by_its_address_is_a_candidate_for_review(): void
    {
        config()->set('services.imports.dedupe_with_ai', false);

        $kosice = Municipality::query()->where('slug', 'kosice')->firstOrFail();

        $existing = Venue::factory()->create([
            'name' => 'Kultúrny dom',
            'street' => 'Hlavná ulica 5',
            'category' => null,
            'village_id' => $kosice->id,
        ]);

        $resolver = app(DuplicateResolver::class);

        // Rovnaká adresa nie je dôkaz — bez AI sa nezlúči, len čaká na kontrolu.
        $this->assertNull($resolver->findVenue('Hlavná 5, Košice', $kosice->id));
        $this->assertDatabaseHas('duplicate_decisions', [
            'entity' => 'venue',
            'candidate_id' => $existing->id,
            'decision' => DuplicateDecision::UNCERTAIN,
        ]);
    }

    #[Test]
    public function a_venue_at_the_same_address_is_reused_on_a_confident_verdict(): void
    {
        $this->fakeJudge(true, 0.93, 'Rovnaká adresa, jediný objekt.');

        $kosice = Municipality::query()->where('slug', 'kosice')->firstOrFail();

        $existing = Venue::factory()->create([
            'name' => 'Kultúrny dom',
            'street' => 'Hlavná 5',
            'category' => null,
            'village_id' => $kosice->id,
        ]);

        $found = app(DuplicateResolver::class)->findVenue('Sála', $kosice->id, 'Hlavná ulica 5');

        $this->assertSame($existing->id, $found?->id);
    }

    #[Test]
    public function a_precise_point_next_to_a_venue_is_a_candidate_even_without_a_shared_word(): void
    {
        $this->fakeJudge(true, 0.9);

        $kosice = Municipality::query()->where('slug', 'kosice')->firstOrFail();

        $existing = Venue::factory()->create([
            'name' => 'Kultúrny dom',
            'street' => null,
            'category' => null,
            'village_id' => $kosice->id,
            'latitude' => 48.72080,
            'longitude' => 21.25750,
            'coordinates_source' => 'venue',
        ]);

        $resolver = app(DuplicateResolver::class);

        // Odhad polohy nestačí, presná adresa áno.
        $this->assertNull($resolver->findVenue('Sála', $kosice->id, null, 48.72085, 21.25755, null, 'municipality'));
        $this->assertSame($existing->id, $resolver->findVenue('Sála', $kosice->id, null, 48.72085, 21.25755, null, 'address')?->id);
    }

    #[Test]
    public function address_keys_ignore_the_street_word_and_the_town_suffix(): void
    {
        $this->assertSame(['hlavna-5'], DuplicateResolver::addressKeys('Košice', 'Hlavná ulica 5, Košice'));
        $this->assertSame(['hlavna-5'], DuplicateResolver::addressKeys(null, 'hlavna 5', 'Hlavná 5'));
        $this->assertSame([], DuplicateResolver::addressKeys('Košice', 'Hlavná ulica', 'Kultúrny dom', null));
        // Obec bez ulíc: súpisné číslo patrí obci.
        $this->assertSame(['pecenice-1552'], DuplicateResolver::addressKeys('Pečenice', '1552', 'Pečenice 1552'));
    }

    #[Test]
    public function an_ai_failure_does_not_block_the_import(): void
    {
        config()->set('openai.api_key', 'test-key');
        config()->set('services.imports.dedupe_with_ai', true);
        Http::fake(['api.openai.com/*' => Http::response('boom', 500)]);

        Canal::factory()->create(['name' => 'Farnosť Nitra – Kalvária']);

        $this->assertNull(app(DuplicateResolver::class)->findCanal('Kalvária Nitra farnosť'));
        $this->assertDatabaseCount('duplicate_decisions', 0);
    }

    #[Test]
    public function the_review_command_lists_pending_decisions_and_can_close_them(): void
    {
        $row = DuplicateDecision::query()->create([
            'entity' => 'canal',
            'input_key' => 'kalvaria-nitra',
            'input_name' => 'Kalvária Nitra',
            'candidate_id' => 1,
            'candidate_name' => 'Farnosť Nitra – Kalvária',
            'created_id' => 2,
            'decision' => DuplicateDecision::UNCERTAIN,
            'confidence' => 0.6,
            'source' => DuplicateDecision::SOURCE_AI,
            'reason' => 'Možno rovnaká farnosť.',
        ]);

        $this->artisan('imports:duplicates')->expectsOutputToContain('Kalvária Nitra')->assertSuccessful();

        $this->artisan('imports:duplicates', ['--distinct' => [$row->id]])
            ->expectsOutputToContain('Žiadne nejasné duplicity')
            ->assertSuccessful();

        $this->assertSame(DuplicateDecision::DISTINCT, $row->fresh()->decision);
        $this->assertNotNull($row->fresh()->reviewed_at);
    }
}
