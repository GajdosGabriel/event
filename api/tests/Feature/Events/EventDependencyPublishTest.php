<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Exceptions\DependenciesNotPublishedException;
use App\Models\Event;
use App\Models\Venue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Publikované podujatie musí mať publikované miesto aj kanál — inak jeho karta
 * odkazuje na profil, ktorý sa tvári ako rozrobený.
 */
class EventDependencyPublishTest extends EventSetupTest
{
    private function draftVenueEvent(): Event
    {
        $venue = Venue::factory()->forCanal($this->canalPrimary->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);

        return Event::query()->create([
            'name' => 'Dependency check ' . uniqid(),
            'status' => ModelStatus::Draft->value,
            'canal_id' => $this->canalPrimary->id,
            'user_id' => $this->user->id,
            'venue_id' => $venue->id,
            'start_at' => now()->addDays(3)->startOfHour(),
            'end_at' => now()->addDays(3)->addHours(2)->startOfHour(),
        ]);
    }

    #[Test]
    public function publishing_over_a_draft_venue_is_refused_with_a_machine_readable_code(): void
    {
        $event = $this->draftVenueEvent();

        $this->postJson('/api/dashboard/events/' . $event->id . '/publish')
            ->assertStatus(422)
            ->assertJsonPath('code', DependenciesNotPublishedException::CODE)
            ->assertJsonPath('dependencies.0.type', 'venue');

        $this->assertSame(ModelStatus::Draft, $event->fresh()->status);
    }

    #[Test]
    public function consent_publishes_the_venue_along_with_the_event(): void
    {
        $this->user->givePermissionTo('venue.update');
        $event = $this->draftVenueEvent();

        $this->postJson('/api/dashboard/events/' . $event->id . '/publish', [
            'publish_dependencies' => true,
        ])->assertOk();

        $this->assertSame(ModelStatus::Published, $event->fresh()->status);
        $this->assertSame(ModelStatus::Published, $event->venue->fresh()->status);
    }

    /** Gate musí platiť aj pre priame uloženie formulára, nielen pre /publish. */
    #[Test]
    public function saving_the_form_as_published_hits_the_same_gate(): void
    {
        $event = $this->draftVenueEvent();

        $this->putJson('/api/dashboard/events/' . $event->id, [
            'name' => $event->name,
            'status' => ModelStatus::Published->value,
            'canal_id' => $event->canal_id,
            'venue_id' => $event->venue_id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', DependenciesNotPublishedException::CODE);

        $this->assertSame(ModelStatus::Draft, $event->fresh()->status);
    }

    #[Test]
    public function saving_the_form_with_consent_publishes_both(): void
    {
        $this->user->givePermissionTo('venue.update');
        $event = $this->draftVenueEvent();

        $this->putJson('/api/dashboard/events/' . $event->id, [
            'name' => $event->name,
            'status' => ModelStatus::Published->value,
            'canal_id' => $event->canal_id,
            'venue_id' => $event->venue_id,
            'publish_dependencies' => true,
        ])->assertOk();

        $this->assertSame(ModelStatus::Published, $event->fresh()->status);
        $this->assertSame(ModelStatus::Published, $event->venue->fresh()->status);
    }

    /**
     * Stiahnutie z webu závislosti nerieši — dole musí ísť podujatie aj vtedy,
     * keď mu medzitým vypadlo miesto.
     */
    #[Test]
    public function unpublishing_ignores_dependencies(): void
    {
        $event = $this->draftVenueEvent();
        $event->forceFill([
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
        ])->save();

        $this->postJson('/api/dashboard/events/' . $event->id . '/publish', ['published' => false])
            ->assertOk();

        $this->assertSame(ModelStatus::Draft, $event->fresh()->status);
    }
}
