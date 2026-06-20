<?php

namespace Tests\Unit\Venues;

use App\Services\Places\WikipediaPlaceEnricher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WikipediaPlaceEnricherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    #[Test]
    public function enrich_returns_official_name_description_image_and_website(): void
    {
        Config::set('services.wikipedia.cache_ttl', 3600);

        Http::fake([
            'https://sk.wikipedia.org/w/api.php*' => Http::response([
                'query' => [
                    'search' => [
                        ['title' => 'Dóm svätého Martina (Bratislava)'],
                    ],
                ],
            ], 200),
            'https://sk.wikipedia.org/api/rest_v1/page/summary/*' => Http::response([
                'title' => 'Dóm svätého Martina (Bratislava)',
                'wikibase_item' => 'Q1268294',
                'extract' => 'Dóm svätého Martina je gotická katedrála v Bratislave. Patrí medzi najvýznamnejšie sakrálne stavby na Slovensku. Nachádza sa v historickom jadre mesta. V minulosti bol korunovačným chrámom uhorských kráľov. Dnes je významným symbolom mesta a cieľom návštevníkov.',
                'thumbnail' => [
                    'source' => 'https://upload.wikimedia.org/example.jpg',
                ],
                'content_urls' => [
                    'desktop' => [
                        'page' => 'https://sk.wikipedia.org/wiki/D%C3%B3m_sv%C3%A4t%C3%A9ho_Martina_(Bratislava)',
                    ],
                ],
            ], 200),
            'https://www.wikidata.org/wiki/Special:EntityData/*' => Http::response([
                'entities' => [
                    'Q1268294' => [
                        'claims' => [
                            'P856' => [
                                [
                                    'mainsnak' => [
                                        'datavalue' => [
                                            'value' => 'https://dom.fara.sk',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = (new WikipediaPlaceEnricher())->enrich('Katedrala sv. Martina', 'Bratislava', 'Slovakia');

        $this->assertSame('Dóm svätého Martina (Bratislava)', $result['official_name']);
        $this->assertSame('https://upload.wikimedia.org/example.jpg', $result['image_url']);
        $this->assertSame('https://dom.fara.sk', $result['website']);
        $this->assertSame('https://sk.wikipedia.org/wiki/D%C3%B3m_sv%C3%A4t%C3%A9ho_Martina_(Bratislava)', $result['reference_url']);
        $this->assertSame('wikipedia', $result['enrichment_source']);
        $this->assertNotNull($result['object_description']);
    }

    #[Test]
    public function enrich_uses_cache_for_identical_queries(): void
    {
        Config::set('services.wikipedia.cache_ttl', 3600);

        Http::fake([
            'https://sk.wikipedia.org/w/api.php*' => Http::response([
                'query' => [
                    'search' => [
                        ['title' => 'Cached Place'],
                    ],
                ],
            ], 200),
            'https://sk.wikipedia.org/api/rest_v1/page/summary/*' => Http::response([
                'title' => 'Cached Place',
                'wikibase_item' => 'Q1',
                'extract' => 'Cached description.',
            ], 200),
            'https://www.wikidata.org/wiki/Special:EntityData/*' => Http::response([
                'entities' => [
                    'Q1' => [
                        'claims' => [
                            'P856' => [
                                [
                                    'mainsnak' => [
                                        'datavalue' => [
                                            'value' => 'https://cached.example',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $enricher = new WikipediaPlaceEnricher();

        $first = $enricher->enrich('Cached Place', 'Bratislava');
        $second = $enricher->enrich('Cached Place', 'Bratislava');

        $this->assertSame($first, $second);
        Http::assertSentCount(5);
    }

    #[Test]
    public function enrich_uses_wikipedia_external_links_when_wikidata_website_is_missing(): void
    {
        Config::set('services.wikipedia.cache_ttl', 3600);

        Http::fake([
            'https://sk.wikipedia.org/w/api.php*' => Http::sequence()
                ->push([
                    'query' => [
                        'search' => [
                            ['title' => 'Test Place'],
                        ],
                    ],
                ], 200)
                ->push([
                    'query' => [
                        'pages' => [
                            '1' => [
                                'extlinks' => [
                                    ['*' => 'https://official-place.example'],
                                ],
                            ],
                        ],
                    ],
                ], 200),
            'https://sk.wikipedia.org/api/rest_v1/page/summary/*' => Http::response([
                'title' => 'Test Place',
                'wikibase_item' => 'Q999',
                'extract' => 'Test description.',
                'content_urls' => [
                    'desktop' => [
                        'page' => 'https://sk.wikipedia.org/wiki/Test_Place',
                    ],
                ],
            ], 200),
            'https://www.wikidata.org/wiki/Special:EntityData/*' => Http::response([
                'entities' => [
                    'Q999' => [
                        'claims' => [],
                    ],
                ],
            ], 200),
        ]);

        $result = (new WikipediaPlaceEnricher())->enrich('Test Place', 'Bratislava');

        $this->assertSame('https://official-place.example', $result['website']);
        $this->assertSame('https://sk.wikipedia.org/wiki/Test_Place', $result['reference_url']);
    }
}
