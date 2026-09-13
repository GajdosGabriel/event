<?php

namespace Tests\Feature\Imports;

use App\Services\Imports\OrganizerWebsiteFinder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganizerWebsiteFinderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('services.imports.organizer_website_lookup', true);
    }

    #[Test]
    public function it_takes_the_article_link_whose_domain_is_the_organizer_acronym(): void
    {
        Http::fake();

        $website = app(OrganizerWebsiteFinder::class)->find(
            'Misijná škola Karola Wojtylu',
            ['https://www.vyveska.sk/podujatie', 'https://www.facebook.com/mskw'],
            'Viac informácií a prihlasovanie na www.mskw.sk a na Facebooku.',
            null,
            'https://www.tkkbs.sk/view.php?cisloclanku=20260707001',
        );

        $this->assertSame('https://www.mskw.sk', $website);
        Http::assertNothingSent();
    }

    #[Test]
    public function the_source_website_is_never_the_organizer_website(): void
    {
        Http::fake(['*' => Http::response(['search' => []])]);

        $website = app(OrganizerWebsiteFinder::class)->find(
            'Vyveska',
            ['https://www.vyveska.sk'],
            'Pozvánka z www.vyveska.sk',
            'https://www.vyveska.sk',
            'https://www.vyveska.sk/podujatie',
        );

        $this->assertNull($website);
    }

    #[Test]
    public function an_ai_website_is_accepted_only_when_the_article_contains_it(): void
    {
        Http::fake(['*' => Http::response(['search' => []])]);
        $finder = app(OrganizerWebsiteFinder::class);

        $this->assertSame(
            'https://www.pastoracia.sk',
            $finder->find('Centrum pre rodinu', [], 'Prihlášky na www.pastoracia.sk/kurz.', 'pastoracia.sk'),
        );

        $this->assertNull($finder->find('Centrum pre rodinu', [], 'Prihlášky u farára.', 'https://www.vymyslene.sk'));
    }

    #[Test]
    public function a_city_domain_does_not_match_a_parish_in_that_city(): void
    {
        Http::fake(['*' => Http::response(['search' => []])]);

        $this->assertNull(app(OrganizerWebsiteFinder::class)->find('Farnosť Košice-Sever', ['https://www.kosice.sk']));
    }

    #[Test]
    public function it_falls_back_to_the_official_website_from_wikidata(): void
    {
        Http::fake([
            'www.wikidata.org/w/api.php*' => Http::response(['search' => [
                ['id' => 'Q1', 'label' => 'Katolícka univerzita v Ružomberku – Teologická fakulta'],
                ['id' => 'Q2', 'label' => 'Katolícka univerzita v Ružomberku'],
            ]]),
            'www.wikidata.org/wiki/Special:EntityData/Q2.json' => Http::response(['entities' => ['Q2' => ['claims' => [
                'P856' => [['mainsnak' => ['datavalue' => ['value' => 'https://www.ku.sk/']]]],
            ]]]]),
        ]);

        $this->assertSame('https://www.ku.sk', app(OrganizerWebsiteFinder::class)->find('Katolícka univerzita v Ružomberku'));
    }

    #[Test]
    public function wikidata_is_not_asked_when_the_lookup_is_disabled(): void
    {
        config()->set('services.imports.organizer_website_lookup', false);
        Http::fake();

        $this->assertNull(app(OrganizerWebsiteFinder::class)->find('Katolícka univerzita v Ružomberku'));
        Http::assertNothingSent();
    }
}
