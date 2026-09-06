<?php

namespace Tests\Feature\Seo;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Support\PublicUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tvar adresy podujatia.
 *
 * Detail sa presťahoval z `/podujatia/{slug}-{id}` na `/akcie/{id}/{slug}`,
 * aby id malo vlastný segment — ten istý tvar bude používať aj hlascirkvi.sk,
 * keď začne podujatia brať z tejto databázy.
 *
 * Staré adresy sú zaindexované a rozposlané v e-mailoch, takže crawler ich
 * musí naďalej dostať vykreslené; presmerovanie na novú podobu rieši 301
 * v .htaccess a Vue Router.
 */
class EventUrlShapeTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->future()->create([
            'name' => 'Koncert v katedrále',
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subMonth(),
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function prerender(string $path)
    {
        return $this->get('/api/prerender?path='.urlencode($path));
    }

    #[Test]
    public function event_path_puts_the_id_in_its_own_segment(): void
    {
        $this->assertSame(
            "akcie/{$this->event->id}/{$this->event->slug}",
            PublicUrl::eventPath($this->event)
        );
    }

    #[Test]
    public function an_event_without_a_slug_falls_back_to_the_bare_id(): void
    {
        // Bez tejto vetvy by adresa skončila lomkou navyše (`/akcie/12/`).
        $this->event->forceFill(['slug' => '', 'name' => ''])->saveQuietly();

        $this->assertSame(
            "akcie/{$this->event->id}",
            PublicUrl::eventPath($this->event->fresh())
        );
    }

    #[Test]
    public function only_events_moved_venues_and_canals_keep_the_old_shape(): void
    {
        // Zmena bola zámerne úzka: prerobiť aj miesta a organizátorov by
        // znamenalo ďalšie presmerovania bez dôvodu.
        $venue = Venue::factory()->create();
        $canal = Canal::factory()->create();

        $this->assertSame("miesta/{$venue->slug}-{$venue->id}", PublicUrl::venuePath($venue));
        $this->assertSame("organizatori/{$canal->slug}-{$canal->id}", PublicUrl::canalPath($canal));
    }

    #[Test]
    public function the_new_address_is_rendered_for_crawlers(): void
    {
        $this->prerender(PublicUrl::eventPath($this->event))
            ->assertOk()
            ->assertSee('Koncert v katedrále', false);
    }

    #[Test]
    public function a_bare_id_without_the_slug_still_renders(): void
    {
        $this->prerender("akcie/{$this->event->id}")
            ->assertOk()
            ->assertSee('Koncert v katedrále', false);
    }

    #[Test]
    public function an_indexed_address_in_the_old_shape_still_renders(): void
    {
        // Crawler s odkazom z indexu nesmie dostať 404 — kanonický odkaz
        // v odpovedi ho presmeruje na nový tvar.
        $this->prerender("podujatia/koncert-v-katedrale-{$this->event->id}")
            ->assertOk()
            ->assertSee('Koncert v katedrále', false);
    }

    #[Test]
    public function the_old_address_points_its_canonical_link_at_the_new_one(): void
    {
        $this->prerender("podujatia/koncert-v-katedrale-{$this->event->id}")
            ->assertSee('<link rel="canonical" href="'.PublicUrl::event($this->event).'">', false);
    }

    #[Test]
    public function landing_pages_moved_under_the_new_prefix(): void
    {
        $this->assertSame('akcie/archiv', PublicUrl::archivePath());
        $this->assertSame('akcie/tento-vikend', PublicUrl::thisWeekendPath());

        $this->prerender('/'.PublicUrl::archivePath())->assertOk();
        $this->prerender('/'.PublicUrl::thisWeekendPath())->assertOk();
    }

    #[Test]
    public function landing_pages_in_the_old_shape_still_render(): void
    {
        $this->prerender('/podujatia/archiv')->assertOk();
        $this->prerender('/podujatia/tento-vikend')->assertOk();
    }
}
