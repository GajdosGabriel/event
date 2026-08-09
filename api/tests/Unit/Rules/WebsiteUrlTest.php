<?php

namespace Tests\Unit\Rules;

use App\Rules\WebsiteUrl;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Jednotné pravidlo pre pole „Web" (App\Rules\WebsiteUrl).
 *
 * Kľúčové je, čo pravidlo **nerobí**: nekontroluje, či stránka odpovedá. To sa
 * deje až na pozadí (App\Services\Attributes) — cudzí server môže byť práve
 * vypnutý a odmietnuť kvôli tomu uloženie formulára by bolo nepochopiteľné.
 */
class WebsiteUrlTest extends TestCase
{
    private function fails(mixed $value): bool
    {
        return Validator::make(['website' => $value], ['website' => [new WebsiteUrl()]])->fails();
    }

    #[Test]
    public function it_accepts_addresses_the_way_people_type_them(): void
    {
        $this->assertFalse($this->fails('divadlo.sk'));
        $this->assertFalse($this->fails('www.divadlo.sk'));
        $this->assertFalse($this->fails('https://divadlo.sk/program'));
        $this->assertFalse($this->fails('http://divadlo.sk'));
        $this->assertFalse($this->fails('kúpele.sk'));
    }

    #[Test]
    public function an_empty_value_is_fine(): void
    {
        // Web je nepovinný — o povinnosti rozhoduje `required`, nie toto pravidlo.
        $this->assertFalse($this->fails(null));
        $this->assertFalse($this->fails(''));
    }

    #[Test]
    public function it_rejects_what_cannot_be_a_public_address(): void
    {
        $this->assertTrue($this->fails('toto nie je adresa'));
        $this->assertTrue($this->fails('localhost'));
        $this->assertTrue($this->fails('javascript:alert(1)'));
        $this->assertTrue($this->fails('127.0.0.1'));
    }

    #[Test]
    public function an_unreachable_address_still_passes(): void
    {
        // Doména neexistuje, tvar je v poriadku — formulár sa uloží a majiteľ
        // sa o probléme dozvie z e-mailu, nie z červeného poľa pod prstami.
        $this->assertFalse($this->fails('https://tento-web-naozaj-neexistuje-98765.sk'));
    }
}
