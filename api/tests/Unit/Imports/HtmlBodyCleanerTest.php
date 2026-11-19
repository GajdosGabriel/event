<?php

namespace Tests\Unit\Imports;

use App\Services\Imports\HtmlBodyCleaner;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HtmlBodyCleanerTest extends TestCase
{
    private HtmlBodyCleaner $cleaner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleaner = new HtmlBodyCleaner;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    #[Test]
    public function it_drops_the_tkkbs_portal_footer_but_keeps_the_sources_under_their_heading(): void
    {
        $html = $this->cleaner->cleanHtmlString(
            '<p>Púť sa začína o 9:00.</p>'
            .'<h3>Zdroje a ďalšie informácie</h3>'
            .'<p>Zdroje: Bratislavská arcidiecéza/Cliptime, redakčne upravené | Upozorniť na chybu v správe | [naspäť] O nás | Ochrana osobných údajov | Copyright | Výveska | Do kostola Nastavenie Cookies</p>'
            .'<ul><li>(c) TK KBS 2003 - 2026</li></ul>'
        );

        $this->assertSame("<p>Púť sa začína o 9:00.</p>\n<h3>Zdroje a ďalšie informácie</h3>\n<p>Zdroje: Bratislavská arcidiecéza/Cliptime, redakčne upravené</p>", $html);
    }

    #[Test]
    public function it_drops_the_heading_when_only_the_tkkbs_portal_footer_was_under_it(): void
    {
        $html = $this->cleaner->cleanHtmlString(
            '<p>Púť sa začína o 9:00.</p>'
            .'<h3>Zdroje a ďalšie informácie</h3>'
            .'<p> | Upozorniť na chybu v správe | O nás | Ochrana osobných údajov | Nastavenie Cookies (c) TK KBS 2003 - 2026</p>'
        );

        $this->assertSame('<p>Púť sa začína o 9:00.</p>', $html);
    }

    #[Test]
    public function it_drops_the_tkkbs_portal_footer_rendered_as_a_list(): void
    {
        $html = $this->cleaner->cleanHtmlString(
            '<p>Viac na www.misijnediela.sk.</p><h3>Odkazy a informácie</h3>'
            .'<ul><li>Upozorniť na chybu v správe</li><li>[naspäť]</li><li>O nás</li><li>Nastavenie Cookies</li></ul>'
            .'<p>(c) TK KBS 2003 - 2026</p>'
        );

        $this->assertSame('<p>Viac na www.misijnediela.sk.</p>', $html);

        $html = $this->cleaner->cleanHtmlString(
            '<p>Text.</p><h3>Dôležité odkazy</h3>'
            .'<ul><li>Hovorca KBS</li><li>Zoznam hovorcov diecéz</li><li>Do kostola</li></ul>'
            .'<p><strong>Nastavenie Cookies:</strong> (c) TK KBS 2003 - 2026</p>'
            .'<p><strong>Kontakt:</strong> 20260910015</p>'
        );

        $this->assertSame('<p>Text.</p>', $html);
    }

    #[Test]
    public function it_wraps_loose_text_left_over_after_unwrapping_into_paragraphs(): void
    {
        $html = $this->cleaner->cleanHtmlString('<div>Prvý odstavec.<br><br>Druhý odstavec.</div>');

        $this->assertSame("<p>Prvý odstavec.</p>\n<p>Druhý odstavec.</p>", $html);
    }

    #[Test]
    public function it_treats_a_non_breaking_space_between_line_breaks_as_a_paragraph_boundary(): void
    {
        // ECAV separates paragraphs with "<br>&nbsp;<br>" rather than <p>.
        $html = $this->cleaner->cleanHtmlString("<div>Prvý.<br>\u{00A0}<br>Druhý.</div>");

        $this->assertSame("<p>Prvý.</p>\n<p>Druhý.</p>", $html);
    }

    #[Test]
    public function it_keeps_a_single_line_break_inside_one_paragraph(): void
    {
        $html = $this->cleaner->cleanHtmlString('<div>Prvý riadok.<br>Druhý riadok.</div>');

        $this->assertSame('<p>Prvý riadok.<br>Druhý riadok.</p>', $html);
    }

    #[Test]
    public function it_splits_a_paragraph_the_source_merged_with_line_breaks(): void
    {
        $html = $this->cleaner->cleanHtmlString('<p>Prvý.<br><br>Druhý.</p>');

        $this->assertSame("<p>Prvý.</p>\n<p>Druhý.</p>", $html);
    }

    #[Test]
    public function it_does_not_add_whitespace_of_its_own_after_an_inline_tag(): void
    {
        $html = $this->cleaner->cleanHtmlString('<div>Slávnosť vedie <strong>Karmen Želinská</strong>, spevom obohatí zbor.</div>');

        $this->assertSame('<p>Slávnosť vedie <strong>Karmen Želinská</strong>, spevom obohatí zbor.</p>', $html);
    }

    #[Test]
    public function it_collapses_the_sources_own_line_wrapping_inside_a_paragraph(): void
    {
        $html = $this->cleaner->cleanHtmlString("<div>Prvý\n   riadok\n\tzdrojového textu.</div>");

        $this->assertSame('<p>Prvý riadok zdrojového textu.</p>', $html);
    }

    #[Test]
    public function it_preserves_existing_block_structure(): void
    {
        $html = $this->cleaner->cleanHtmlString('<div><p>Odstavec.</p><h2>Nadpis</h2><ul><li>Položka</li></ul></div>');

        $this->assertStringContainsString('<p>Odstavec.</p>', $html);
        $this->assertStringContainsString('<h2>Nadpis</h2>', $html);
        $this->assertStringContainsString('<li>Položka</li>', $html);
    }

    #[Test]
    public function it_does_not_wrap_whitespace_between_blocks_into_empty_paragraphs(): void
    {
        $html = $this->cleaner->cleanHtmlString("<div><p>Prvý.</p>\n\n  \n<p>Druhý.</p></div>");

        $this->assertSame("<p>Prvý.</p>\n<p>Druhý.</p>", $html);
    }

    #[Test]
    public function it_wraps_loose_text_that_sits_next_to_a_block(): void
    {
        $html = $this->cleaner->cleanHtmlString('<div>Úvodná veta.<h2>Nadpis</h2>Záverečná veta.</div>');

        $this->assertSame("<p>Úvodná veta.</p>\n<h2>Nadpis</h2>\n<p>Záverečná veta.</p>", $html);
    }

    #[Test]
    public function it_skips_excluded_nodes_and_their_descendants(): void
    {
        $xpath = $this->xpath('<div id="event"><p class="creator"><strong>Akciu pridal:</strong> Anna</p><p>Obsah.</p></div>');

        $html = $this->cleaner->cleanFromXPath($xpath, '//*[@id="event"]', '//p[contains(@class, "creator")]');

        $this->assertSame('<p>Obsah.</p>', $html);
    }

    #[Test]
    public function it_leaves_the_dom_intact_so_excluded_nodes_stay_readable_afterwards(): void
    {
        $xpath = $this->xpath('<div id="event"><h2>10.04.2026 17:00-18:30</h2><p>Obsah.</p></div>');

        $this->cleaner->cleanFromXPath($xpath, '//*[@id="event"]', '//h2');

        $this->assertSame(1, $xpath->query('//h2')->length);
    }

    #[Test]
    public function it_does_not_carry_exclusions_over_to_a_later_call(): void
    {
        $xpath = $this->xpath('<div id="event"><p class="creator">Anna</p><p>Obsah.</p></div>');
        $this->cleaner->cleanFromXPath($xpath, '//*[@id="event"]', '//p[contains(@class, "creator")]');

        $html = $this->cleaner->cleanFromXPath($xpath, '//*[@id="event"]');

        $this->assertStringContainsString('Anna', $html);
    }
}
