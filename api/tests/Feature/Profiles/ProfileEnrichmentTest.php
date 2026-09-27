<?php

namespace Tests\Feature\Profiles;

use App\Enums\CanalIdentityMode;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\AiUsage;
use App\Models\Canal;
use App\Models\Municipality;
use App\Models\ProfileEnrichment;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\ProfileCompleted;
use App\Services\Geocoding\NominatimGeocoder;
use App\Services\Profiles\ProfileCoordinates;
use App\Services\Profiles\ProfileEnricher;
use App\Services\Profiles\ProfileResearch;
use App\Support\NationwideCoordinates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['openai.api_key' => 'test-key', 'profile_enrichment.enabled' => true, 'profile_enrichment.monthly_limit_usd' => 1]);
        Notification::fake();
        \Illuminate\Support\Facades\DB::table('municipalities')->insertOrIgnore(['id' => 900001,
            'fullname' => 'Testovce', 'shortname' => 'Testovce', 'zip' => '90001', 'district_id' => 1, 'region_id' => 1,
        ]);
    }

    private function canal(array $attributes = []): Canal
    {
        return Canal::query()->create($attributes + [
            'name' => 'Organizátor Testovce', 'identity_mode' => CanalIdentityMode::Organization,
            'registration_source' => RegistrationSource::SELF, 'status' => ModelStatus::Published,
            'municipality_id' => 900001, 'created_at' => now()->subHours(3),
            'latitude' => 48.1, 'longitude' => 17.1, 'coordinates_source' => 'manual',
        ]);
    }

    private function venue(Canal $owner, array $attributes = []): Venue
    {
        $venue = Venue::query()->create($attributes + [
            'name' => 'Dom kultúry Testovce', 'village_id' => 900001,
            'status' => ModelStatus::Draft, 'created_at' => now()->subHours(3),
            'latitude' => 48.1, 'longitude' => 17.1, 'coordinates_source' => 'manual',
        ]);
        $venue->assignCanal($owner, isOwner: true);

        return $venue;
    }

    private function owner(Canal $canal): User
    {
        $owner = User::factory()->create();
        $canal->users()->attach($owner->id, ['is_owner' => true, 'status' => ModelStatus::Published->value, 'role' => 'owner']);

        return $owner;
    }

    private function entry(string $value): array
    {
        return ['value' => $value, 'source_url' => 'https://example.sk/kontakt', 'evidence' => 'Oficiálny kontakt profilu.'];
    }

    public function test_fills_only_missing_canal_fields_and_notifies_owner_once(): void
    {
        $canal = $this->canal(['email' => 'vlastny@example.sk']);
        $owner = $this->owner($canal);
        $this->mock(ProfileResearch::class)->shouldReceive('research')->once()->andReturn([
            'email' => $this->entry('cudzi@example.sk'), 'website' => $this->entry('https://example.sk/organizator'),
            'body' => $this->entry('Organizácia pomáha rodinám.'),
        ]);
        $service = app(ProfileEnricher::class);
        $service->run($canal);
        $service->run($canal);
        $this->assertSame('vlastny@example.sk', $canal->fresh()->email);
        $this->assertSame('https://example.sk/organizator', $canal->fresh()->website);
        $this->assertSame('manual', $canal->fresh()->coordinates_source);
        $this->assertArrayHasKey('website', ProfileEnrichment::sole()->evidence);
        Notification::assertSentToTimes($owner, ProfileCompleted::class, 1);
        Notification::assertSentTo($owner, ProfileCompleted::class, function ($notification) use ($owner) {
            $this->assertStringContainsString('Skontrolovať profil', (string) $notification->toMail($owner)->render());
            $this->assertArrayNotHasKey('email', $notification->changes);

            return true;
        });
    }

    public function test_user_owned_venue_is_enriched_and_owner_is_notified(): void
    {
        $canal = $this->canal();
        $owner = $this->owner($canal);
        $venue = $this->venue($canal, ['phone' => '+421 900 111 222']);
        $this->mock(ProfileResearch::class)->shouldReceive('research')->once()->andReturn([
            'email' => $this->entry('prevadzka@example.sk'), 'phone' => $this->entry('+421 900 333 444'),
        ]);
        app(ProfileEnricher::class)->run($venue);
        $this->assertSame('prevadzka@example.sk', $venue->fresh()->email);
        $this->assertSame('+421 900 111 222', $venue->fresh()->phone);
        $this->assertSame(ModelStatus::Draft, $venue->fresh()->status);
        Notification::assertSentToTimes($owner, ProfileCompleted::class, 1);
    }

    public function test_imported_venue_does_not_email_the_technical_owner(): void
    {
        $canal = $this->canal(['registration_source' => RegistrationSource::IMPORT]);
        $this->owner($canal);
        $venue = $this->venue($canal);
        $this->mock(ProfileResearch::class)->shouldReceive('research')->once()->andReturn(['email' => $this->entry('info@example.sk')]);
        app(ProfileEnricher::class)->run($venue);
        $this->assertSame('info@example.sk', $venue->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_command_waits_two_hours_and_skips_personal_fallback_and_blocked(): void
    {
        $recent = $this->canal(['created_at' => now()->subMinutes(119)]);
        $this->canal(['name' => 'Osobný', 'identity_mode' => CanalIdentityMode::Personal]);
        $this->canal(['name' => 'Blokovaný', 'status' => ModelStatus::Blocked]);
        $this->venue($recent, ['category' => 'fallback']);
        $this->mock(ProfileResearch::class)->shouldNotReceive('research');
        $this->artisan('app:profiles-enrich')->assertSuccessful();
        $this->assertDatabaseCount('profile_enrichments', 0);
    }

    public function test_user_edit_during_research_is_preserved(): void
    {
        $canal = $this->canal();
        $this->mock(ProfileResearch::class)->shouldReceive('research')->once()->andReturnUsing(function () use ($canal) {
            $canal->fresh()->update(['email' => 'user@example.sk']);

            return ['email' => $this->entry('ai@example.sk'), 'body' => $this->entry('Overený popis.')];
        });
        app(ProfileEnricher::class)->run($canal);
        $this->assertSame('user@example.sk', $canal->fresh()->email);
        $this->assertStringContainsString('Overený popis.', $canal->fresh()->body);
    }

    public function test_new_manual_pin_during_lookup_prevents_stale_write(): void
    {
        $canal = $this->canal(['coordinates_source' => 'municipality']);
        $this->mock(ProfileResearch::class)->shouldReceive('research')->once()->andReturn([]);
        $this->mock(ProfileCoordinates::class)->shouldReceive('resolve')->once()->andReturnUsing(function () use ($canal) {
            $canal->fresh()->update(['latitude' => 49.2, 'longitude' => 19.2, 'coordinates_source' => 'manual']);

            return ['latitude' => 48.2, 'longitude' => 18.2, 'coordinates_source' => 'address', 'source_url' => 'https://example.sk', 'evidence' => 'Adresa'];
        });
        app(ProfileEnricher::class)->run($canal);
        $this->assertEquals(49.2, $canal->fresh()->latitude);
        $this->assertSame('manual', $canal->fresh()->coordinates_source);
        $this->assertNull(ProfileEnrichment::sole()->completed_at);
    }

    public function test_placeholder_pin_is_replaced_with_verified_address_coordinates(): void
    {
        $canal = $this->canal(['latitude' => NationwideCoordinates::LATITUDE, 'longitude' => NationwideCoordinates::LONGITUDE, 'coordinates_source' => 'municipality', 'street' => 'Hlavná 1']);
        $this->mock(ProfileResearch::class)->shouldReceive('research')->once()->andReturn([]);
        $this->mock(NominatimGeocoder::class)->shouldReceive('lookupAddress')->once()->andReturn([
            'latitude' => 48.5, 'longitude' => 18.5, 'city' => 'Testovce', 'street' => 'Hlavná 1',
        ]);
        app(ProfileEnricher::class)->run($canal);
        $this->assertEquals(48.5, $canal->fresh()->latitude);
        $this->assertEquals(18.5, $canal->fresh()->longitude);
        $this->assertSame('address', $canal->fresh()->coordinates_source);
        $this->assertArrayHasKey('latitude', ProfileEnrichment::sole()->evidence);
    }

    public function test_venue_uses_matched_building_but_organization_never_uses_event_venue(): void
    {
        $canal = $this->canal(['coordinates_source' => 'municipality']);
        $venue = $this->venue($canal, ['coordinates_source' => 'municipality']);
        $this->mock(NominatimGeocoder::class)->shouldReceive('lookup')->once()->andReturn([
            'latitude' => 48.5, 'longitude' => 18.5, 'city' => 'Testovce', 'street' => 'Hlavná 1',
        ]);
        $service = app(ProfileCoordinates::class);
        $this->assertSame([], $service->resolve($canal, []));
        $this->assertSame('venue', $service->resolve($venue, [])['coordinates_source']);
    }

    public function test_rejects_wrong_city_and_preserves_manual_placeholder(): void
    {
        $canal = $this->canal(['coordinates_source' => 'municipality', 'street' => 'Hlavná 1']);
        $this->mock(NominatimGeocoder::class)->shouldReceive('lookupAddress')->once()->andReturn([
            'latitude' => 48.5, 'longitude' => 18.5, 'city' => 'Iná obec', 'street' => 'Hlavná 1',
        ]);
        $service = app(ProfileCoordinates::class);
        $this->assertSame([], $service->resolve($canal, []));
        $canal->forceFill(['latitude' => NationwideCoordinates::LATITUDE, 'longitude' => NationwideCoordinates::LONGITUDE, 'coordinates_source' => 'manual']);
        $this->assertFalse($service->needsLookup($canal));
    }

    public function test_exhausted_budget_and_disabled_feature_make_no_request(): void
    {
        $canal = $this->canal();
        $this->mock(ProfileResearch::class)->shouldNotReceive('research');
        config(['profile_enrichment.enabled' => false]);
        app(ProfileEnricher::class)->run($canal);
        config(['profile_enrichment.enabled' => true]);
        AiUsage::create(['feature' => ProfileResearch::FEATURE, 'model' => 'gpt-4.1-mini', 'cost_usd' => 1, 'prompt_tokens' => 0, 'completion_tokens' => 0, 'success' => true]);
        app(ProfileEnricher::class)->run($canal);
        Http::assertNothingSent();
    }

    public function test_no_result_completes_without_email_and_failure_is_backed_off(): void
    {
        $canal = $this->canal();
        $this->mock(ProfileResearch::class)->shouldReceive('research')->once()->andReturn([]);
        app(ProfileEnricher::class)->run($canal);
        app(ProfileEnricher::class)->run($canal);
        $this->assertNotNull(ProfileEnrichment::sole()->completed_at);
        Notification::assertNothingSent();
        $other = $this->canal(['name' => 'Druhý']);
        $this->mock(ProfileResearch::class)->shouldReceive('research')->once()->andThrow(new \RuntimeException('API unavailable'));
        $service = app(ProfileEnricher::class);
        $service->run($other);
        $service->run($other);
        $audit = ProfileEnrichment::where('subject_id', $other->id)->firstOrFail();
        $this->assertNull($audit->completed_at);
        $this->assertSame(1, $audit->attempts);
        $this->assertTrue($audit->retry_at->isFuture());
    }

    public function test_scheduler_processes_both_models_and_does_not_repeat_finished_profiles(): void
    {
        $canal = $this->canal(['registration_source' => RegistrationSource::IMPORT]);
        $venue = $this->venue($canal);
        $this->mock(ProfileResearch::class)->shouldReceive('research')->twice()->andReturn(['email' => $this->entry('kontakt@example.sk')]);
        $this->artisan('app:profiles-enrich')->assertSuccessful();
        $this->artisan('app:profiles-enrich')->assertSuccessful();
        $this->assertSame('kontakt@example.sk', $canal->fresh()->email);
        $this->assertSame('kontakt@example.sk', $venue->fresh()->email);
        $this->assertDatabaseCount('profile_enrichments', 2);
        Notification::assertNothingSent();
    }

    public function test_mail_failure_is_retried_after_one_hour_without_paid_research(): void
    {
        $canal = $this->canal();
        $owner = $this->owner($canal);
        $this->mock(ProfileResearch::class)->shouldReceive('research')->once()->andReturn(['email' => $this->entry('kontakt@example.sk')]);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Mail unavailable'));
        $service = app(ProfileEnricher::class);
        $service->run($canal);
        $service->run($canal);
        $this->assertNull(ProfileEnrichment::sole()->notified_at);
        $this->assertSame(1, ProfileEnrichment::sole()->notification_attempts);
        $this->travel(61)->minutes();
        Notification::fake();
        $service->run($canal);
        Notification::assertSentToTimes($owner, ProfileCompleted::class, 1);
        $this->assertNotNull(ProfileEnrichment::sole()->notified_at);
    }

    public function test_verified_city_replaces_only_the_nationwide_placeholder(): void
    {
        $canal = $this->canal(['municipality_id' => Municipality::nationwideId()]);
        $this->mock(ProfileResearch::class)->shouldReceive('research')->once()->andReturn(['city' => $this->entry('Testovce')]);
        app(ProfileEnricher::class)->run($canal);
        $this->assertEquals(900001, $canal->fresh()->municipality_id);
        $this->assertArrayHasKey('municipality_id', ProfileEnrichment::sole()->evidence);
    }

    public function test_incomplete_ai_response_is_logged_but_does_not_save_fields(): void
    {
        $canal = $this->canal();
        Http::fake(['api.openai.com/*' => Http::response([
            'model' => 'gpt-4.1-mini', 'status' => 'incomplete',
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 200],
            'output' => [['type' => 'web_search_call', 'action' => ['sources' => []]]],
        ])]);
        app(ProfileEnricher::class)->run($canal);
        $this->assertNull($canal->fresh()->email);
        $this->assertFalse(AiUsage::sole()->success);
        $this->assertNull(ProfileEnrichment::sole()->completed_at);
        $this->assertGreaterThan(0.01, AiUsage::sole()->cost_usd);
    }

    public function test_research_validates_real_sources_and_records_web_search_cost(): void
    {
        $canal = $this->canal();
        $entry = $this->entry('info@example.sk') + ['official_source' => true];
        $payload = ['identity_match' => true, 'fields' => ['email' => $entry, 'latitude' => $this->entry('48.5')]];
        Http::fake(['api.openai.com/*' => Http::response([
            'model' => 'gpt-4.1-mini', 'status' => 'completed',
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 200],
            'output' => [
                ['type' => 'web_search_call', 'action' => ['sources' => [['url' => $entry['source_url']]]]],
                ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($payload)]]],
            ],
        ])]);
        $result = app(ProfileResearch::class)->research($canal, ['email', 'latitude']);
        $this->assertSame('info@example.sk', $result['email']['value']);
        $this->assertArrayNotHasKey('latitude', $result);
        $this->assertEqualsWithDelta(0.01072, AiUsage::sole()->cost_usd, 0.000001);
        $this->assertSame($canal->id, AiUsage::sole()->subject_id);
        Http::assertSent(fn ($request) => $request['tool_choice'] === 'required' && $request['store'] === false);
        $this->assertSame([], app(ProfileResearch::class)->verifiedFields($payload, [], ['email']));
        $payload['identity_match'] = false;
        $this->assertSame([], app(ProfileResearch::class)->verifiedFields($payload, [$entry['source_url']], ['email']));
    }
}
