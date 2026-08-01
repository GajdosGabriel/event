<?php

namespace Tests\Feature\Seo;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\User;
use App\Support\PublicUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bot-render vrstva. Zmyslom testov je jediné tvrdenie: crawler musí dostať
 * názov, obrázok a JSON-LD **v HTML odpovedi**, nie až po spustení JS.
 */
class PrerenderTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();

        $this->event = Event::factory()->future()->create([
            'name' => 'Koncert v katedrále',
            'body' => '<p>Vianočný koncert s <script>alert(1)</script> orchestrom.</p>',
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subMonth(),
            'user_id' => $user->id,
        ]);
    }

    private function prerender(string $path)
    {
        return $this->get('/api/prerender?path='.urlencode($path));
    }

    #[Test]
    public function event_detail_renders_open_graph_tags_in_html(): void
    {
        $response = $this->prerender(PublicUrl::eventPath($this->event));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $response->assertSee('<meta property="og:title" content="Koncert v katedrále">', false);
        $response->assertSee('<meta property="og:type" content="article">', false);
        $response->assertSee('<title>Koncert v katedrále | '.config('app.name').'</title>', false);
    }

    #[Test]
    public function event_detail_renders_json_ld_event(): void
    {
        $response = $this->prerender(PublicUrl::eventPath($this->event));

        $response->assertOk();
        $response->assertSee('application/ld+json', false);

        $schema = $this->firstJsonLd($response->getContent());

        $this->assertSame('Event', $schema['@type']);
        $this->assertSame('Koncert v katedrále', $schema['name']);
        $this->assertSame($this->event->start_at->format('Y-m-d\TH:i:s'), $schema['startDate']);
        $this->assertSame(PublicUrl::event($this->event), $schema['url']);
    }

    /**
     * Kanonická adresa je vždy slugová, aj keď crawler doraziť môže na starú
     * číselnú — inak by sa v indexe usadili dve adresy toho istého podujatia.
     */
    #[Test]
    public function legacy_numeric_path_renders_with_canonical_slug_url(): void
    {
        $response = $this->prerender('/events/'.$this->event->id);

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="'.PublicUrl::event($this->event).'">', false);
    }

    #[Test]
    public function unpublished_event_is_not_rendered(): void
    {
        $this->event->update(['status' => ModelStatus::Draft->value]);

        $this->prerender(PublicUrl::eventPath($this->event))->assertNotFound();
    }

    #[Test]
    public function unknown_path_returns_not_found(): void
    {
        $this->prerender('/nieco-co-neexistuje')->assertNotFound();
    }

    /**
     * `body` sa ukladá bez sanitizácie, takže prerender ho musí prečistiť sám —
     * je to jediná verejná HTML odpoveď, ktorú aplikácia vracia.
     */
    #[Test]
    public function event_body_is_sanitised_before_rendering(): void
    {
        $response = $this->prerender(PublicUrl::eventPath($this->event));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('orchestrom', false);
    }

    #[Test]
    public function event_list_renders_item_list_schema(): void
    {
        $response = $this->prerender('/'.PublicUrl::eventsPath());

        $response->assertOk();

        $schema = $this->firstJsonLd($response->getContent());

        $this->assertSame('ItemList', $schema['@type']);
        $this->assertGreaterThan(0, $schema['numberOfItems']);
    }

    #[Test]
    public function weekend_landing_page_renders(): void
    {
        $response = $this->prerender('/'.PublicUrl::thisWeekendPath());

        $response->assertOk();
        $response->assertSee('Podujatia tento víkend', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function firstJsonLd(string $html): array
    {
        $this->assertSame(
            1,
            preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches),
            'Odpoveď neobsahuje JSON-LD.',
        );

        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
