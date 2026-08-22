<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\Ticket;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Archivované podujatie sa nedá upraviť ani zmazať a archivuje sa automaticky
 * desať minút po skončení — preklep v roku ho teda zamkne skôr, než si to
 * niekto všimne. `unarchive` je jediná cesta späť; viď EventPolicy::unarchive().
 */
class DashboardEventUnarchiveTest extends EventSetupTest
{
    private function archivedEvent(): Event
    {
        return Event::query()->create([
            'name' => 'Archived ' . uniqid(),
            'status' => ModelStatus::Archived->value,
            'canal_id' => (int) $this->canalPrimary->id,
            'user_id' => $this->user->id,
            'published_at' => now()->subMonth(),
        ]);
    }

    #[Test]
    public function owner_can_return_archived_event_to_drafts(): void
    {
        $event = $this->archivedEvent();

        $this->postJson('/api/dashboard/events/' . $event->id . '/unarchive')
            ->assertOk()
            ->assertJsonPath('status', ModelStatus::Draft->value);

        $fresh = $event->fresh();

        $this->assertSame(ModelStatus::Draft, $fresh->status);
        // Verejné scope-y filtrujú podľa stavu aj podľa published_at — musia ísť
        // dole spolu, inak by podujatie ostalo visieť vo výpisoch.
        $this->assertNull($fresh->published_at);
    }

    /** Po odomknutí sa dá termín konečne opraviť — kvôli tomu to celé je. */
    #[Test]
    public function unarchived_event_becomes_editable_again(): void
    {
        $event = $this->archivedEvent();

        $this->putJson('/api/dashboard/events/' . $event->id, ['name' => 'Opravené'])
            ->assertForbidden();

        $this->postJson('/api/dashboard/events/' . $event->id . '/unarchive')->assertOk();

        $this->putJson('/api/dashboard/events/' . $event->id, ['name' => 'Opravené'])
            ->assertOk()
            ->assertJsonPath('name', 'Opravené');
    }

    /**
     * Vydaný lístok je záväzok voči návštevníkovi: koncept verejnosť nevidí,
     * takže by držiteľovi lístka zmizol detail akcie, na ktorú prišiel.
     */
    #[Test]
    public function event_with_issued_tickets_stays_locked(): void
    {
        $event = $this->archivedEvent();

        Ticket::query()->create([
            'event_id' => $event->id,
            'holder_name' => 'Jozef Návštevník',
            'holder_email' => 'jozef@example.test',
        ]);

        $this->postJson('/api/dashboard/events/' . $event->id . '/unarchive')
            ->assertForbidden();

        $this->assertSame(ModelStatus::Archived, $event->fresh()->status);
    }

    #[Test]
    public function only_archived_events_can_be_unarchived(): void
    {
        $draft = Event::query()->create([
            'name' => 'Draft ' . uniqid(),
            'status' => ModelStatus::Draft->value,
            'canal_id' => (int) $this->canalPrimary->id,
            'user_id' => $this->user->id,
        ]);

        $this->postJson('/api/dashboard/events/' . $draft->id . '/unarchive')
            ->assertForbidden();
    }

    /**
     * Cudzie podujatie sa v dashboarde ani nenačíta — dashboardShow() filtruje
     * na kanály používateľa, takže odomykanie skončí na 404 ešte pred policy.
     */
    #[Test]
    public function foreign_event_cannot_be_unarchived(): void
    {
        $this->cudziEvent->forceFill(['status' => ModelStatus::Archived->value])->save();

        $this->postJson('/api/dashboard/events/' . $this->cudziEvent->id . '/unarchive')
            ->assertNotFound();
    }

    /** Tlačidlo v menu akcií riadi výhradne tento príznak. */
    #[Test]
    public function show_exposes_the_unarchive_permission(): void
    {
        $archived = $this->archivedEvent();

        $this->getJson('/api/dashboard/events/' . $archived->id)
            ->assertOk()
            ->assertJsonPath('permissions.unarchive', true)
            ->assertJsonPath('permissions.update', false);

        // Vlastný koncept, nie $this->futureEvent — tomu EventFactory losuje stav
        // naprieč všetkými prípadmi ModelStatus a vedel by vyjsť archivovaný.
        $draft = Event::query()->create([
            'name' => 'Draft ' . uniqid(),
            'status' => ModelStatus::Draft->value,
            'canal_id' => (int) $this->canalPrimary->id,
            'user_id' => $this->user->id,
        ]);

        $this->getJson('/api/dashboard/events/' . $draft->id)
            ->assertOk()
            ->assertJsonPath('permissions.unarchive', false);
    }
}
