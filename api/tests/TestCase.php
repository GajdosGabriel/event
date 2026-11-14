<?php

namespace Tests;

use App\Support\Url;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Rate limity sú vypnuté pre celú test suite — počítadlá sú zdieľané cez
        // cache a inak by testy padali podľa poradia spustenia. Testy, ktoré samotné
        // limitovanie overujú, si ho zapnú cez `$this->withMiddleware(ThrottleRequests::class)`.
        $this->withoutMiddleware(ThrottleRequests::class);

        // Podvrhnutý preklad hostov je statický — nesmie prežiť do ďalšieho testu.
        Url::resolveHostsUsing(null);

        // Žiadny test nesmie ísť na skutočnú sieť. Uloženie kanála volá
        // geokóder (backfillCoordinates), ktorý na verejnom Nominatime čakal
        // až do rozpočtu 15 s — dvakrát na kanál. Na CI tak jeden test trval
        // 30 s a celá suita 14 minút. Testy, ktoré sieť potrebujú, si ju
        // fakujú cez Http::fake(); nefakované volanie tu hneď vyhodí výnimku,
        // ktorú geokóder aj ostatné služby zachytia ako nedostupnú službu.
        Http::preventStrayRequests();

        // Spatie si tabuľku oprávnení cachuje. CACHE_STORE=array prežíva celý
        // PHPUnit proces, kým RefreshDatabase medzitým databázu resetuje — cache
        // potom drží ID, ktoré už neexistujú, a givePermissionTo() priradí
        // oprávnenie, ktoré sa pri kontrole neuplatní. Prejaví sa to ako 403,
        // ktoré závisí od poradia testov.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Každý host sa „preloží" na verejnú IP — bez skutočného DNS. Potrebné
     * pre všetko, čo ide cez SafeRemoteFetcher (`*.test` by inak neprešlo
     * a skutočné domény by test spomalili dotazmi do siete).
     */
    protected function fakePublicDns(): void
    {
        Url::resolveHostsUsing(fn (string $host) => ['93.184.216.34']);
    }

    /**
     * Skutočný malý obrázok — prílohy sa overujú podľa obsahu, text
     * s hlavičkou `image/jpeg` neprejde. Iný $seed = iný obsah (checksum).
     */
    protected function fakeImage(string $format = 'jpeg', string $seed = ''): string
    {
        $image = imagecreatetruecolor(2, 2);
        imagefill($image, 0, 0, crc32($seed) & 0xFFFFFF);

        ob_start();
        match ($format) {
            'png' => imagepng($image),
            'gif' => imagegif($image),
            'webp' => imagewebp($image),
            default => imagejpeg($image),
        };

        return (string) ob_get_clean();
    }
}
