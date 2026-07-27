<?php

namespace Tests\Feature\Tags;

use App\Models\Tag;
use App\Models\TicketType;
use App\Services\Tags\EventAttributeDeriver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Facet „charakter" sa odvádza z dát, nie z AI — model ho halucinoval aj pri
 * explicitnom zákaze. Tieto testy pripínajú pravidlá odvodenia.
 */
class EventAttributeDeriverTest extends EventSetupTest
{
    private EventAttributeDeriver $deriver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deriver = new EventAttributeDeriver();

        foreach ([['vonku', 'Vonku'], ['vstup-volny', 'Vstup voľný'], ['s-registraciou', 'S registráciou'], ['viacdnove', 'Viacdňové'], ['online', 'Online']] as $index => [$slug, $name]) {
            Tag::query()->create([
                'group' => 'attribute',
                'slug' => $slug,
                'name' => $name,
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }
    }

    #[Test]
    public function single_day_event_stored_across_two_utc_dates_is_not_multi_day(): void
    {
        // Jednodňová akcia 5. 9. sa v lete uloží ako 4. 9. 22:00 → 5. 9. 21:59
        // UTC. Bez prepočtu na Europe/Bratislava vyzerala ako dvojdňová a na
        // reálnych dátach to označilo 56 z 91 podujatí.
        $this->futureEvent->update([
            'start_at' => '2026-09-04 22:00:00',
            'end_at' => '2026-09-05 21:59:00',
        ]);

        $this->assertNotContains('viacdnove', $this->deriver->derive($this->futureEvent->fresh()));
    }

    #[Test]
    public function genuinely_multi_day_event_is_detected(): void
    {
        $this->futureEvent->update([
            'start_at' => '2026-07-23 22:00:00',
            'end_at' => '2026-07-26 21:59:00',
        ]);

        $this->assertContains('viacdnove', $this->deriver->derive($this->futureEvent->fresh()));
    }

    #[Test]
    public function missing_price_does_not_mean_free(): void
    {
        // Presne na tomto sa mýlil model: cena nebola spomenutá, tak usúdil,
        // že vstup je voľný.
        $this->futureEvent->update(['price_amount' => null]);

        $this->assertNotContains('vstup-volny', $this->deriver->derive($this->futureEvent->fresh()));
    }

    #[Test]
    public function zero_price_means_free(): void
    {
        $this->futureEvent->update(['price_amount' => 0]);

        $this->assertContains('vstup-volny', $this->deriver->derive($this->futureEvent->fresh()));
    }

    #[Test]
    public function active_ticket_type_means_registration(): void
    {
        $this->assertNotContains('s-registraciou', $this->deriver->derive($this->futureEvent->fresh()));

        TicketType::query()->create([
            'event_id' => $this->futureEvent->id,
            'name' => 'Vstupenka',
            'kind' => 'ticket',
            'price_amount' => 500,
            'is_active' => true,
        ]);

        $this->assertContains('s-registraciou', $this->deriver->derive($this->futureEvent->fresh()));
    }

    #[Test]
    public function online_requires_an_explicit_word_in_the_text(): void
    {
        $this->futureEvent->update([
            'name' => 'Púť na Bobrovskú kalváriu',
            'body' => 'Stretnutie sa začne slávnostnou svätou omšou na kalvárii.',
        ]);

        $this->assertNotContains('online', $this->deriver->derive($this->futureEvent->fresh()));

        $this->futureEvent->update(['name' => 'Online seminár – Boh a emócie']);

        $this->assertContains('online', $this->deriver->derive($this->futureEvent->fresh()));
    }

    #[Test]
    public function sync_replaces_derived_tags_but_keeps_manual_ones(): void
    {
        $manual = Tag::query()->where('slug', 'online')->firstOrFail();

        $this->futureEvent->tags()->attach([
            $manual->id => ['confidence' => 100, 'source' => 'manual', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->futureEvent->update([
            'name' => 'Festival v parku',
            'body' => 'Trojdňová prehliadka.',
            'start_at' => '2026-07-23 22:00:00',
            'end_at' => '2026-07-26 21:59:00',
        ]);

        $this->deriver->sync($this->futureEvent->fresh());

        $rows = $this->futureEvent->fresh()->tags()->get();

        $this->assertSame('manual', $rows->firstWhere('slug', 'online')->pivot->source);
        $this->assertSame('derived', $rows->firstWhere('slug', 'viacdnove')->pivot->source);
        $this->assertSame('derived', $rows->firstWhere('slug', 'vonku')->pivot->source);
    }
}
