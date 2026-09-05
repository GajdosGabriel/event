<?php

namespace Tests\Unit\Attributes;

use App\Services\Attributes\Probes\WebsiteProbe;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ako sonda rozhoduje, či web „funguje" (App\Services\Attributes\Probes).
 *
 * Najdôležitejšie sú tu tolerované odpovede. Sonda nemá dokazovať, že sa
 * stránka dá prečítať robotom — má zistiť, či za adresou niekto je. Keby sa
 * 403 od Cloudflare počítalo za chybu, portál by rozposlal majiteľom väčších
 * webov obvinenia z pokazeného webu, ktorý funguje úplne v poriadku.
 *
 * HTTP je podvrhnuté; DNS nie — `example.com` sa musí dať preložiť, inak
 * sonda skončí skôr, než sa k odpovedi dostane (a to je jej správne správanie).
 */
class WebsiteProbeTest extends TestCase
{
    private const URL = 'https://example.com';

    private function probe(): WebsiteProbe
    {
        return app(WebsiteProbe::class);
    }

    #[Test]
    public function a_normal_response_means_the_site_works(): void
    {
        Http::fake([ '*' => Http::response('', 200) ]);

        $result = $this->probe()->probe(self::URL);

        $this->assertTrue($result->ok);
        $this->assertSame(200, $result->httpStatus);
    }

    #[Test]
    public function a_site_that_blocks_robots_still_counts_as_working(): void
    {
        foreach ([401, 403, 405, 429] as $status) {
            Http::fake([ '*' => Http::response('', $status) ]);

            $this->assertTrue($this->probe()->probe(self::URL)->ok, "HTTP $status");
        }
    }

    /**
     * Regresia, ktorá stála 114 falošných hlásení o pokazenom webe.
     *
     * WordPress — a s ním väčšina webov, ktoré organizátori uvádzajú —
     * presmeruje `…/kurz` na kanonické `…/kurz/`. Kým si sonda cieľ
     * presmerovania preháňala cez Url::normalize(), ktorý koncovú lomku
     * zámerne orezáva, odpovedala serveru zakaždým tou istou adresou bez
     * lomky. Server presmeroval znova a po piatich skokoch z toho bol
     * `redirect_loop` na úplne funkčnom webe.
     */
    #[Test]
    public function a_redirect_to_the_canonical_trailing_slash_is_followed(): void
    {
        Http::fake([
            'https://example.com/kurz' => Http::response('', 301, ['Location' => 'https://example.com/kurz/']),
            'https://example.com/kurz/' => Http::response('', 200),
        ]);

        $result = $this->probe()->probe('https://example.com/kurz/');

        $this->assertTrue($result->ok, 'Kanonizácia lomkou nie je zacyklenie.');
        $this->assertSame(200, $result->httpStatus);
    }

    /** Skutočné zacyklenie musí sonda naďalej rozpoznať. */
    #[Test]
    public function a_real_redirect_loop_is_still_a_failure(): void
    {
        Http::fake([
            'https://example.com/a' => Http::response('', 302, ['Location' => 'https://example.com/b']),
            'https://example.com/b' => Http::response('', 302, ['Location' => 'https://example.com/a']),
        ]);

        $result = $this->probe()->probe('https://example.com/a');

        $this->assertFalse($result->ok);
        $this->assertSame('redirect_loop', $result->reason);
    }

    #[Test]
    public function a_missing_page_is_a_failure(): void
    {
        Http::fake([ '*' => Http::response('', 404) ]);

        $result = $this->probe()->probe(self::URL);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->reason);
        $this->assertSame(404, $result->httpStatus);
    }

    #[Test]
    public function a_server_error_is_a_failure(): void
    {
        Http::fake([ '*' => Http::response('', 503) ]);

        $result = $this->probe()->probe(self::URL);

        $this->assertFalse($result->ok);
        $this->assertSame('server_error', $result->reason);
    }

    #[Test]
    public function a_head_request_that_fails_is_retried_as_get(): void
    {
        // Nemálo serverov HEAD neovláda a odbaví ho chybou, hoci stránka žije.
        Http::fakeSequence()
            ->push('', 405)
            ->push('', 200);

        $this->assertTrue($this->probe()->probe(self::URL)->ok);
    }

    #[Test]
    public function redirects_are_followed(): void
    {
        Http::fakeSequence()
            ->push('', 301, ['Location' => 'https://example.com/sk'])
            ->push('', 200);

        $this->assertTrue($this->probe()->probe(self::URL)->ok);
    }

    #[Test]
    public function a_redirect_loop_is_a_failure(): void
    {
        Http::fake([ '*' => Http::response('', 301, ['Location' => 'https://example.com/sk']) ]);

        $result = $this->probe()->probe(self::URL);

        $this->assertFalse($result->ok);
        $this->assertSame('redirect_loop', $result->reason);
    }

    #[Test]
    public function a_timeout_is_recognised(): void
    {
        // Dôvod ide majiteľovi do e-mailu ako veta, ktorej rozumie — preto sa
        // hláška z knižnice prekladá na vlastný kľúč a nikdy neodchádza surová.
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->assertSame('timeout', $this->probe()->probe(self::URL)->reason);
    }

    #[Test]
    public function a_certificate_problem_is_recognised(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 35: SSL connect error'));

        $this->assertSame('ssl', $this->probe()->probe(self::URL)->reason);
    }

    #[Test]
    public function a_nonexistent_domain_never_reaches_http(): void
    {
        Http::fake([ '*' => Http::response('', 200) ]);

        $result = $this->probe()->probe('https://tento-web-naozaj-neexistuje-98765.sk');

        $this->assertFalse($result->ok);
        $this->assertSame('dns', $result->reason);
        Http::assertNothingSent();
    }

    #[Test]
    public function an_address_pointing_inside_the_network_is_refused(): void
    {
        Http::fake([ '*' => Http::response('', 200) ]);

        // Sonda chodí na adresy zadané cudzími ľuďmi. Bez tejto poistky by
        // stačilo do formulára napísať vnútornú adresu a nechať si ju overiť.
        $this->assertFalse($this->probe()->probe('http://127.0.0.1/admin')->ok);
        Http::assertNothingSent();
    }
}
