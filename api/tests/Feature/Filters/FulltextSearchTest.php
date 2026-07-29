<?php

namespace Tests\Feature\Filters;

use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FULLTEXT vetvu `HasCommonFilters::scopeBySearch()` sa nedá otestovať v transakcii:
 * InnoDB dopĺňa index až pri commite, takže riadky zapísané v neuzavretej transakcii
 * by MATCH nevidel (a trait to vie, preto v transakcii vedome používa LIKE).
 *
 * Táto trieda preto zámerne nepoužíva `DatabaseTransactions` a po sebe upratuje
 * ručne — všetky vytvorené záznamy sa v tearDown() natvrdo mažú.
 */
class FulltextSearchTest extends TestCase
{
    private User $user;

    private Canal $canal;

    /** @var array<int, int> */
    private array $eventIds = [];

    private string $marker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->marker = 'ftmarker' . bin2hex(random_bytes(4));

        $this->user = User::factory()->create();
        $this->canal = Canal::factory()->create();
    }

    protected function tearDown(): void
    {
        Event::withTrashed()->whereIn('id', $this->eventIds)->forceDelete();
        $this->canal->forceDelete();
        $this->user->forceDelete();

        parent::tearDown();
    }

    private function makeEvent(string $name, ?string $body = null): Event
    {
        $event = Event::query()->create([
            'name' => $name,
            'body' => $body,
            'user_id' => $this->user->id,
            'canal_id' => $this->canal->id,
        ]);

        $this->eventIds[] = $event->id;

        return $event;
    }

    #[Test]
    public function search_matches_words_in_any_order_and_across_columns(): void
    {
        $match = $this->makeEvent(
            'Spomienka na sestru ' . $this->marker,
            'Podujatie sa kona v Kosiciach na hrade',
        );

        $missingSecondWord = $this->makeEvent(
            'Spomienka na sestru ' . $this->marker,
            'Podujatie sa kona v Bratislave',
        );

        // Presne to, co stary LIKE '%term%' nenasiel: obe slova su v zazname,
        // ale nie vedla seba a ani nie v tom istom stlpci.
        $results = Event::query()->applyCommonFilters(['search' => $this->marker . ' Kosiciach'])->get();

        $this->assertTrue($results->contains('id', $match->id));
        $this->assertFalse($results->contains('id', $missingSecondWord->id));
    }

    #[Test]
    public function search_matches_word_prefix(): void
    {
        $event = $this->makeEvent('Vecerny koncert ' . $this->marker);

        $results = Event::query()->applyCommonFilters(['search' => $this->marker . ' konce'])->get();

        $this->assertTrue($results->contains('id', $event->id));
    }

    #[Test]
    public function search_orders_name_matches_before_body_matches(): void
    {
        $bodyMatch = $this->makeEvent('Podujatie bez markera', 'V popise je ' . $this->marker);
        $nameMatch = $this->makeEvent('Podujatie ' . $this->marker);

        $results = Event::query()->applyCommonFilters(['search' => $this->marker])->get();

        $this->assertSame($nameMatch->id, $results->first()?->id);
        $this->assertTrue($results->contains('id', $bodyMatch->id));
    }

    #[Test]
    public function search_falls_back_to_like_for_words_shorter_than_index_token(): void
    {
        // Dvojznakove slovo v indexe nie je (innodb_ft_min_token_size = 3),
        // takze musi nastupit LIKE — inak by hladanie vratilo prazdno.
        $event = $this->makeEvent('Podujatie ' . $this->marker . ' Qx');

        $results = Event::query()->applyCommonFilters(['search' => 'Qx'])->get();

        $this->assertTrue($results->contains('id', $event->id));
    }

    #[Test]
    public function search_ignores_boolean_mode_operators(): void
    {
        $event = $this->makeEvent('Podujatie ' . $this->marker);

        // Operatory boolean modu sa pri tokenizacii zahodia, takze dopyt
        // nespadne na syntakticku chybu ani nezmeni vyznam hladania.
        $results = Event::query()->applyCommonFilters(['search' => '+*"(' . $this->marker . ')" -~'])->get();

        $this->assertTrue($results->contains('id', $event->id));
    }
}
