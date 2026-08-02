<?php

namespace Tests\Unit\Events;

use App\Models\Canal;
use App\Models\Event;
use App\Models\Venue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `body` sa vo verejnom UI vykresľuje cez `v-html`, takže čokoľvek, čo sa do
 * stĺpca dostane, sa spustí v prehliadači návštevníka. Čistenie preto sedí na
 * mutatore modelu, nie vo FormRequeste — ten istý stĺpec plnia aj importy,
 * AI popisy z plagátu a admin nástroje, a všetky musia skončiť rovnako.
 *
 * Test beží bez databázy: mutator sa spúšťa pri priradení atribútu, takže na
 * overenie stačí model v pamäti.
 */
class BodySanitizationTest extends TestCase
{
    #[Test]
    public function it_strips_a_script_tag(): void
    {
        $event = new Event;
        $event->body = '<p>Neškodný text.</p><script>alert(1)</script>';

        $this->assertSame('<p>Neškodný text.</p>', $event->body);
    }

    #[Test]
    public function it_strips_event_handler_attributes(): void
    {
        $event = new Event;
        $event->body = '<p onclick="alert(1)">Text</p>';

        $this->assertSame('<p>Text</p>', $event->body);
    }

    #[Test]
    public function it_strips_a_javascript_href_but_keeps_the_link_text(): void
    {
        $event = new Event;
        $event->body = '<p><a href="javascript:alert(1)">klikni</a></p>';

        $this->assertStringNotContainsString('javascript:', $event->body);
        $this->assertStringContainsString('klikni', $event->body);
    }

    #[Test]
    public function it_strips_an_iframe(): void
    {
        $event = new Event;
        $event->body = '<p>Text</p><iframe src="https://evil.example"></iframe>';

        $this->assertStringNotContainsString('<iframe', $event->body);
    }

    #[Test]
    public function it_cleans_the_ai_body_too(): void
    {
        $event = new Event;
        $event->body_ai = '<p>Popis od AI.</p><script>alert(1)</script>';

        $this->assertSame('<p>Popis od AI.</p>', $event->body_ai);
    }

    #[Test]
    public function it_cleans_the_canal_body(): void
    {
        $canal = new Canal;
        $canal->body = '<p>O nás.</p><script>alert(1)</script>';

        $this->assertSame('<p>O nás.</p>', $canal->body);
    }

    #[Test]
    public function it_cleans_the_venue_body(): void
    {
        $venue = new Venue;
        $venue->body = '<p>Popis miesta.</p><script>alert(1)</script>';

        $this->assertSame('<p>Popis miesta.</p>', $venue->body);
    }

    /**
     * Formátovanie z editora v UI (TipTap) musí zápis prežiť — inak by čistenie
     * potichu mazalo to, čo si používateľ práve naklikal.
     */
    #[Test]
    public function it_keeps_the_formatting_the_editor_produces(): void
    {
        $event = new Event;
        $event->body = '<h2>Nadpis</h2>'
            .'<p><strong>tučné</strong> <em>kurzíva</em> <u>podčiarknuté</u> <s>preškrtnuté</s></p>'
            .'<ul><li>položka</li></ul>'
            .'<ol><li>prvá</li></ol>'
            .'<blockquote><p>citát</p></blockquote>'
            .'<hr>'
            .'<p><a href="https://example.com">odkaz</a></p>';

        foreach (['<h2>', '<strong>', '<em>', '<u>', '<s>', '<ul>', '<ol>', '<li>', '<blockquote>', '<hr>', 'href="https://example.com"'] as $needle) {
            $this->assertStringContainsString($needle, $event->body, "Editor produkuje {$needle}, čistenie ho zahodilo.");
        }
    }

    /**
     * Import zapisuje už prečistené HTML a factory generuje popisy cez model —
     * druhý prechod ich nesmie prepísať, inak by sa popis menil pri každom
     * uložení a diff v admine by bol plný falošných zmien.
     */
    #[Test]
    public function cleaning_is_idempotent(): void
    {
        $event = new Event;
        $event->body = "<h2>Nadpis</h2>\n<p>Prvý odstavec.</p>\n<p>Druhý <strong>odstavec</strong>.</p>";
        $once = $event->body;

        $event->body = $once;

        $this->assertSame($once, $event->body);
    }

    /**
     * Popis bez tagov je čistý text — prázdny riadok medzi odstavcami je jediné,
     * čo o štruktúre hovorí, a nesmie sa stratiť.
     */
    #[Test]
    public function it_keeps_paragraph_breaks_of_plain_text(): void
    {
        $event = new Event;
        $event->body = "Prvý odstavec.\n\nDruhý odstavec.";

        $this->assertSame("<p>Prvý odstavec.</p>\n<p>Druhý odstavec.</p>", $event->body);
    }

    #[Test]
    public function it_escapes_plain_text_that_looks_like_a_tag(): void
    {
        $event = new Event;
        $event->body = '<script>alert(1)</script>';

        $this->assertStringNotContainsString('<script', (string) $event->body);
    }

    #[Test]
    public function it_leaves_a_null_body_as_null(): void
    {
        $event = new Event;
        $event->body = null;

        $this->assertNull($event->body);
    }
}
