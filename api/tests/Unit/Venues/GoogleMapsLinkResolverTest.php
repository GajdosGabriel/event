<?php

namespace Tests\Unit\Venues;

use App\Services\Geocoding\GoogleMapsLinkResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleMapsLinkResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    #[Test]
    public function it_follows_short_link_redirect_to_maps_search_coordinates(): void
    {
        Http::fake([
            'maps.app.goo.gl/*' => Http::response('', 302, [
                'Location' => 'https://www.google.com/maps/search/48.298834,+18.092023?entry=tts',
            ]),
        ]);

        $coords = (new GoogleMapsLinkResolver())->resolveUrl('https://maps.app.goo.gl/iNdsVUDXdEruVovG6');

        $this->assertSame(48.298834, $coords['latitude']);
        $this->assertSame(18.092023, $coords['longitude']);
    }

    #[Test]
    public function it_reads_at_coordinates_without_any_network_call(): void
    {
        Http::fake();

        $coords = (new GoogleMapsLinkResolver())
            ->resolveUrl('https://www.google.com/maps/place/Nitra/@48.3061,18.0764,15z');

        $this->assertSame(48.3061, $coords['latitude']);
        $this->assertSame(18.0764, $coords['longitude']);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_reads_bang_3d_4d_coordinates(): void
    {
        Http::fake();

        $coords = (new GoogleMapsLinkResolver())
            ->resolveUrl('https://www.google.com/maps/place/X/data=!3d49.1234!4d21.5678');

        $this->assertSame(49.1234, $coords['latitude']);
        $this->assertSame(21.5678, $coords['longitude']);
    }

    #[Test]
    public function it_reads_coordinates_from_a_consent_continue_redirect(): void
    {
        $target = 'https://www.google.com/maps/search/48.5,+18.5';
        Http::fake([
            'goo.gl/*' => Http::response('', 302, [
                'Location' => 'https://consent.google.com/ml?continue=' . rawurlencode($target) . '&gl=SK',
            ]),
            'consent.google.com/*' => Http::response('', 303, [
                'Location' => $target . '?ucbcb=1',
            ]),
        ]);

        $coords = (new GoogleMapsLinkResolver())->resolveUrl('https://goo.gl/maps/abc123');

        $this->assertSame(48.5, $coords['latitude']);
        $this->assertSame(18.5, $coords['longitude']);
    }

    #[Test]
    public function it_ignores_non_google_maps_urls(): void
    {
        Http::fake();

        $coords = (new GoogleMapsLinkResolver())->fromText('Viac info na https://example.com/podujatie');

        $this->assertNull($coords['latitude']);
        $this->assertNull($coords['longitude']);
        Http::assertNothingSent();
    }

    #[Test]
    public function from_text_extracts_the_first_maps_link(): void
    {
        Http::fake([
            'maps.app.goo.gl/*' => Http::response('', 302, [
                'Location' => 'https://www.google.com/maps/search/48.1,+17.1',
            ]),
        ]);

        $coords = (new GoogleMapsLinkResolver())
            ->fromText("Stretneme sa, presne tu: https://maps.app.goo.gl/xyz\nTešíme sa!");

        $this->assertSame(48.1, $coords['latitude']);
        $this->assertSame(17.1, $coords['longitude']);
    }

    #[Test]
    public function it_rejects_out_of_range_and_null_island_coordinates(): void
    {
        Http::fake();

        $resolver = new GoogleMapsLinkResolver();

        $this->assertNull($resolver->resolveUrl('https://www.google.com/maps/place/@0,0,3z')['latitude']);
        $this->assertNull($resolver->resolveUrl('https://www.google.com/maps/search/999.0,+18.0')['latitude']);
    }
}
