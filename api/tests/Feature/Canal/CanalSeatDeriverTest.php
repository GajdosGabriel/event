<?php

namespace Tests\Feature\Canal;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Venue;
use App\Services\Canals\CanalSeatDeriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Odvodenie obce importovaného kanála z miest jeho podujatí.
 *
 * Import zakladá kanál skôr, než vie čokoľvek o mieste, takže mu obec ostáva
 * na zbernom „Celé Slovensko". Tu sa stráži, že sa doplní práve vtedy, keď je
 * jednoznačná — a že sa nedotkne ničoho, čo zadal človek.
 */
class CanalSeatDeriverTest extends TestCase
{
    use RefreshDatabase;

    private CanalSeatDeriver $deriver;

    private int $nationwideId;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deriver = app(CanalSeatDeriver::class);
        $this->user = User::factory()->create();

        $nationwideId = Municipality::nationwideId();
        $this->assertNotNull($nationwideId, 'Číselník musí obsahovať zberné „Celé Slovensko".');
        $this->nationwideId = $nationwideId;
    }

    private function canal(array $attributes = []): Canal
    {
        return Canal::factory()->create(array_merge([
            'municipality_id' => $this->nationwideId,
            'registration_source' => RegistrationSource::IMPORT->value,
        ], $attributes));
    }

    /**
     * Miesto musí patriť tomuto kanálu: EventFactory::syncLocationFields()
     * prepíše `canal_id` podujatia na kanál miesta, takže miesto z cudzieho
     * kanála by podujatie ticho odsunulo inam a test by nemeral nič.
     */
    private function eventAt(Canal $canal, int $municipalityId): Event
    {
        $venue = Venue::factory()->create([
            'canal_id' => $canal->id,
            'village_id' => $municipalityId,
            'status' => ModelStatus::Published->value,
        ]);

        return Event::factory()->future()->create([
            'canal_id' => $canal->id,
            'venue_id' => $venue->id,
            'user_id' => $this->user->id,
            'status' => ModelStatus::Published->value,
        ]);
    }

    /** Obec, ktorá nie je zbernou hodnotou — na nej sa dá overiť skutočné odvodenie. */
    private function someMunicipality(int $skip = 0): int
    {
        return (int) Municipality::query()
            ->where('id', '<>', $this->nationwideId)
            ->orderBy('id')
            ->skip($skip)
            ->value('id');
    }

    #[Test]
    public function a_single_event_municipality_becomes_the_canal_seat(): void
    {
        $municipalityId = $this->someMunicipality();
        $canal = $this->canal();
        $this->eventAt($canal, $municipalityId);

        $this->assertTrue($this->deriver->sync($canal));
        $this->assertSame($municipalityId, (int) $canal->fresh()->municipality_id);
    }

    #[Test]
    public function events_in_several_municipalities_leave_the_canal_nationwide(): void
    {
        $canal = $this->canal();
        $this->eventAt($canal, $this->someMunicipality(0));
        $this->eventAt($canal, $this->someMunicipality(1));

        $this->assertFalse($this->deriver->sync($canal));
        $this->assertSame($this->nationwideId, (int) $canal->fresh()->municipality_id);
    }

    #[Test]
    public function a_canal_without_events_stays_nationwide(): void
    {
        $canal = $this->canal();

        $this->assertFalse($this->deriver->sync($canal));
        $this->assertSame($this->nationwideId, (int) $canal->fresh()->municipality_id);
    }

    /** Miesto v zbernom „Celé Slovensko" nie je údaj o obci, len chýbajúca hodnota. */
    #[Test]
    public function events_at_the_fallback_venue_do_not_derive_a_seat(): void
    {
        $canal = $this->canal();
        $this->eventAt($canal, $this->nationwideId);

        $this->assertFalse($this->deriver->sync($canal));
        $this->assertSame($this->nationwideId, (int) $canal->fresh()->municipality_id);
    }

    #[Test]
    public function a_self_registered_canal_is_never_touched(): void
    {
        $canal = $this->canal(['registration_source' => RegistrationSource::SELF->value]);
        $this->eventAt($canal, $this->someMunicipality());

        $this->assertFalse($this->deriver->sync($canal));
        $this->assertSame($this->nationwideId, (int) $canal->fresh()->municipality_id);
    }

    /** Ručne zadaná obec sa neprepisuje ani na importovanom kanáli. */
    #[Test]
    public function an_already_set_municipality_is_never_overwritten(): void
    {
        $chosenId = $this->someMunicipality(0);
        $canal = $this->canal(['municipality_id' => $chosenId]);
        $this->eventAt($canal, $this->someMunicipality(1));

        $this->assertFalse($this->deriver->sync($canal));
        $this->assertSame($chosenId, (int) $canal->fresh()->municipality_id);
    }

    /**
     * Sídlo organizátora je iný údaj než miesto konania a má pred ním prednosť:
     * „Západoslovenské múzeum v Trnave" hovorí o organizátorovi, kým miesto
     * patrí jednému podujatiu — a keď ho import trafí zle (rovnomenný kostol
     * v inom meste), odvodenie z neho posadí kanál do cudzej obce.
     */
    #[Test]
    public function the_detected_organizer_city_becomes_the_canal_seat(): void
    {
        $canal = $this->canal();

        $this->assertTrue($this->deriver->applyDetectedCity($canal, 'Trnava'));
        $this->assertSame(
            (int) Municipality::query()->where('slug', 'trnava')->value('id'),
            (int) $canal->fresh()->municipality_id,
        );
    }

    #[Test]
    public function the_organizer_city_overrides_a_seat_derived_from_the_venue(): void
    {
        $canal = $this->canal();
        $this->eventAt($canal, $this->someMunicipality());

        $this->assertTrue($this->deriver->sync($canal));

        $this->assertTrue($this->deriver->applyDetectedCity($canal->fresh(), 'Trnava'));
        $this->assertSame(
            (int) Municipality::query()->where('slug', 'trnava')->value('id'),
            (int) $canal->fresh()->municipality_id,
        );
    }

    /** Ručne vybranú obec neprepíše ani mesto organizátora z článku. */
    #[Test]
    public function a_hand_picked_seat_survives_the_detected_organizer_city(): void
    {
        $chosenId = $this->someMunicipality(0);
        $canal = $this->canal(['municipality_id' => $chosenId]);
        // Podujatie inde než na ručne zadanej obci: kanál teda nesedí na tom,
        // čo by odvodenie z miest vyrobilo samo — hodnotu zadal človek.
        $this->eventAt($canal, $this->someMunicipality(1));

        $this->assertFalse($this->deriver->applyDetectedCity($canal, 'Trnava'));
        $this->assertSame($chosenId, (int) $canal->fresh()->municipality_id);
    }

    #[Test]
    public function a_self_registered_canal_ignores_the_detected_organizer_city(): void
    {
        $canal = $this->canal(['registration_source' => RegistrationSource::SELF->value]);

        $this->assertFalse($this->deriver->applyDetectedCity($canal, 'Trnava'));
        $this->assertSame($this->nationwideId, (int) $canal->fresh()->municipality_id);
    }

    #[Test]
    public function an_empty_organizer_city_changes_nothing(): void
    {
        $canal = $this->canal();

        $this->assertFalse($this->deriver->applyDetectedCity($canal, null));
        $this->assertFalse($this->deriver->applyDetectedCity($canal, '   '));
        $this->assertSame($this->nationwideId, (int) $canal->fresh()->municipality_id);
    }

    #[Test]
    public function a_soft_deleted_event_does_not_count(): void
    {
        $canal = $this->canal();
        $this->eventAt($canal, $this->someMunicipality(0));
        $this->eventAt($canal, $this->someMunicipality(1))->delete();

        $this->assertTrue($this->deriver->sync($canal));
        $this->assertSame($this->someMunicipality(0), (int) $canal->fresh()->municipality_id);
    }
}
