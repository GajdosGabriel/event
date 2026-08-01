<?php

namespace Tests\Feature\Seo;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\User;
use App\Support\PublicUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private Event $published;

    private Event $draft;

    private Event $past;

    protected function setUp(): void
    {
        parent::setUp();

        // Mapa je cachovaná na hodinu — bez vyčistenia by testy videli výsledok
        // predchádzajúceho behu.
        Cache::forget('sitemap.xml');

        $user = User::factory()->create();

        $this->published = Event::factory()->future()->create([
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subMonth(),
            'user_id' => $user->id,
        ]);

        $this->draft = Event::factory()->future()->create([
            'status' => ModelStatus::Draft->value,
            'published_at' => null,
            'user_id' => $user->id,
        ]);

        $this->past = Event::factory()->past()->create([
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subMonths(6),
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function sitemap_is_valid_xml(): void
    {
        $response = $this->get('/api/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $xml = simplexml_load_string($response->getContent());

        $this->assertNotFalse($xml, 'Sitemap nie je validné XML.');
        $this->assertSame('urlset', $xml->getName());
    }

    #[Test]
    public function sitemap_contains_published_upcoming_event(): void
    {
        $this->get('/api/sitemap.xml')
            ->assertOk()
            ->assertSee(PublicUrl::event($this->published), false);
    }

    #[Test]
    public function sitemap_omits_drafts_and_finished_events(): void
    {
        $response = $this->get('/api/sitemap.xml');

        $response->assertOk();
        $response->assertDontSee(PublicUrl::event($this->draft), false);
        $response->assertDontSee(PublicUrl::event($this->past), false);
    }

    #[Test]
    public function sitemap_contains_landing_pages(): void
    {
        $this->get('/api/sitemap.xml')
            ->assertOk()
            ->assertSee(PublicUrl::events(), false)
            ->assertSee(PublicUrl::thisWeekend(), false);
    }
}
