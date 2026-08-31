<?php

namespace Tests\Feature\Console;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReassignVyveskaOrganizersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.imports.describe_with_ai', false);

        Role::findOrCreate('super-admin', 'web');
        User::factory()->create()->assignRole('super-admin');
    }

    private function bucket(): Canal
    {
        return Canal::factory()->create([
            'name' => 'vyveska.sk',
            'slug' => 'vyveskask',
            'website' => 'https://www.vyveska.sk',
            'registration_source' => RegistrationSource::IMPORT->value,
        ]);
    }

    private function eventOnBucket(Canal $bucket, array $meta, array $attributes = []): Event
    {
        return Event::factory()->create([
            'canal_id' => $bucket->id,
            'status' => ModelStatus::Published->value,
            'meta' => $meta,
            ...$attributes,
        ]);
    }

    #[Test]
    public function it_moves_an_event_to_the_organizer_detected_by_the_nightly_ai_run(): void
    {
        $bucket = $this->bucket();
        $event = $this->eventOnBucket($bucket, [
            'ai_detector' => ['event_payload' => ['organizer' => ['name' => 'Spoločenstvo pri Dóme sv. Martina']]],
        ]);

        $this->artisan('app:reassign-vyveska-organizers')->assertSuccessful();

        $event->refresh();
        $this->assertNotSame($bucket->id, $event->canal_id);
        $this->assertSame('Spoločenstvo pri Dóme sv. Martina', $event->canal->name);
        // Miesto podujatia prejde pod nový kanál ako vlastnícke.
        $this->assertTrue(
            $event->venue->canals()->where('canals.id', $event->canal_id)->wherePivot('is_owner', true)->exists(),
        );
    }

    #[Test]
    public function it_reuses_an_existing_canal_instead_of_creating_a_duplicate(): void
    {
        $bucket = $this->bucket();
        $organizer = Canal::factory()->create(['name' => 'Žilinská diecéza', 'slug' => 'zilinska-dieceza']);
        $event = $this->eventOnBucket($bucket, [
            'ai_detector' => ['event_payload' => ['organizer' => ['name' => 'Žilinská diecéza']]],
        ]);

        $before = Canal::query()->count();

        $this->artisan('app:reassign-vyveska-organizers')->assertSuccessful();

        $this->assertSame($before, Canal::query()->count());
        $this->assertSame($organizer->id, $event->refresh()->canal_id);
    }

    #[Test]
    public function it_leaves_events_without_a_detected_organizer_on_the_bucket(): void
    {
        $bucket = $this->bucket();
        $event = $this->eventOnBucket($bucket, ['raw_text' => 'Kedy: 1.1.2027']);

        $this->artisan('app:reassign-vyveska-organizers')->assertSuccessful();

        $this->assertSame($bucket->id, $event->refresh()->canal_id);
    }

    #[Test]
    public function it_leaves_events_whose_detected_organizer_is_too_generic(): void
    {
        $bucket = $this->bucket();
        // „Farský úrad" by sa cez LIKE %názov% nalepilo na náhodný kanál.
        Canal::factory()->create(['name' => 'Rada KBS, Farský úrad Gaboltov', 'slug' => 'rada-kbs-farsky-urad-gaboltov']);
        $event = $this->eventOnBucket($bucket, [
            'ai_detector' => ['event_payload' => ['organizer' => ['name' => 'Farský úrad']]],
        ]);

        $this->artisan('app:reassign-vyveska-organizers')->assertSuccessful();

        $this->assertSame($bucket->id, $event->refresh()->canal_id);
    }

    #[Test]
    public function it_takes_the_organizer_website_from_the_event_but_skips_registration_hosts(): void
    {
        $bucket = $this->bucket();
        $withRealSite = $this->eventOnBucket($bucket, [
            'ai_detector' => ['event_payload' => ['organizer' => ['name' => 'Campfest']]],
        ], ['website' => 'https://campfest.sk/2026/']);
        $withForm = $this->eventOnBucket($bucket, [
            'ai_detector' => ['event_payload' => ['organizer' => ['name' => 'Rodinkovo']]],
        ], ['website' => 'https://docs.google.com/forms/d/e/abc/viewform']);

        $this->artisan('app:reassign-vyveska-organizers')->assertSuccessful();

        $this->assertSame('https://campfest.sk', $withRealSite->refresh()->canal->website);
        $this->assertEmpty($withForm->refresh()->canal->website);
    }

    #[Test]
    public function dry_run_changes_nothing(): void
    {
        $bucket = $this->bucket();
        $event = $this->eventOnBucket($bucket, [
            'ai_detector' => ['event_payload' => ['organizer' => ['name' => 'Godzone']]],
        ]);
        $before = Canal::query()->count();

        $this->artisan('app:reassign-vyveska-organizers --dry-run')->assertSuccessful();

        $this->assertSame($before, Canal::query()->count());
        $this->assertSame($bucket->id, $event->refresh()->canal_id);
    }
}
