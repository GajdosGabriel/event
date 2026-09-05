<?php

namespace Tests\Unit\Imports;

use App\Services\Imports\EventTextLabelExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventTextLabelExtractorTest extends TestCase
{
    private EventTextLabelExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new EventTextLabelExtractor();
    }

    #[Test]
    public function it_extracts_venue_and_city_from_a_label(): void
    {
        $result = $this->extractor->extractVenue('Miesto konania: Sabinov, Gréckokatolícky chrám');

        $this->assertSame('Gréckokatolícky chrám', $result['name']);
        $this->assertSame('Sabinov', $result['city']);
    }

    #[Test]
    public function it_extracts_venue_and_city_from_single_v_prose(): void
    {
        $result = $this->extractor->extractVenue('Stretnutie o 18:00 v Katedrále svätého Martina v Bratislave.');

        $this->assertSame('Katedrále svätého Martina', $result['name']);
        $this->assertSame('Bratislave', $result['city']);
    }

    #[Test]
    public function it_extracts_venue_only_when_no_city_follows(): void
    {
        $result = $this->extractor->extractVenue('Program o 19:00 v Divadle Jonáša Záborského.');

        $this->assertSame('Divadle Jonáša Záborského', $result['name']);
        $this->assertNull($result['city']);
    }

    #[Test]
    public function it_handles_chained_prepositions_across_multiple_lines(): void
    {
        // Real Vyveska announcement (event 121): venue keyword-bearing segment wins,
        // preceding segment ("v Nitre") is the city, "na Kalvárii" is discarded.
        $text = "Pozývame na modlitbové stretnutia každú nedeľu o 18.00\n"
            . "na Kalvárii\nv Nitre\nvo Farskom pastoračnom centre\n"
            . '(vchod z veľkého parkoviska).';

        $result = $this->extractor->extractVenue($text);

        $this->assertSame('Farskom pastoračnom centre', $result['name']);
        $this->assertSame('Nitre', $result['city']);
    }

    #[Test]
    public function it_picks_the_venue_regardless_of_order_relative_to_city(): void
    {
        // Order reversed: venue first, city second.
        $result = $this->extractor->extractVenue('Modlitba o 18.00 vo Farskom kostole v Nitre');

        $this->assertSame('Farskom kostole', $result['name']);
        $this->assertSame('Nitre', $result['city']);
    }

    #[Test]
    public function it_does_not_invent_a_venue_from_generic_prose(): void
    {
        $this->assertNull($this->extractor->extractVenue('Ideme o 9:00 na výlet do Tatier'));
        $this->assertNull($this->extractor->extractVenue('Príďte o 17:00, tešíme sa na vás.'));
    }

    #[Test]
    public function it_extracts_the_organizer_city_from_the_sentence_with_his_name(): void
    {
        $text = 'Trnava 4. septembra (TK KBS) Západoslovenské múzeum v Trnave pripravuje pri'
            . " príležitosti 800. výročia\npodujatie Osem storočí – jeden príbeh.";

        $this->assertSame(
            'Trnave',
            $this->extractor->extractOrganizerCity($text, 'Západoslovenské múzeum'),
        );
    }

    #[Test]
    public function it_reads_a_two_word_organizer_city(): void
    {
        $this->assertSame(
            'Liptovskom Mikuláši',
            $this->extractor->extractOrganizerCity(
                'Cirkevný zbor ECAV v Liptovskom Mikuláši pozýva na koncert.',
                'Cirkevný zbor ECAV',
            ),
        );
    }

    #[Test]
    public function it_does_not_mistake_a_venue_for_the_organizer_city(): void
    {
        // „v Kostole…" za názvom organizátora je miesto konania, nie jeho sídlo.
        $this->assertNull($this->extractor->extractOrganizerCity(
            'Farnosť Prievidza v Kostole svätého Bartolomeja pozýva na koncert.',
            'Farnosť Prievidza',
        ));

        // Pokračovanie vety malým písmenom nie je obec.
        $this->assertNull($this->extractor->extractOrganizerCity(
            'Západoslovenské múzeum v spolupráci s františkánmi pripravuje podujatie.',
            'Západoslovenské múzeum',
        ));
    }
}
