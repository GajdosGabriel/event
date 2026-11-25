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

        $this->extractor = new EventTextLabelExtractor;
    }

    #[Test]
    public function it_extracts_venue_and_city_from_a_label(): void
    {
        $result = $this->extractor->extractVenue('Miesto konania: Sabinov, Gréckokatolícky chrám');

        $this->assertSame('Gréckokatolícky chrám', $result['name']);
        $this->assertSame('Sabinov', $result['city']);
    }

    #[Test]
    public function it_reads_venue_street_and_city_in_vyveska_order(): void
    {
        $result = $this->extractor->extractVenue("Kedy: 18:00 – 20:00\nKde: Dom Quo Vadis, Veterná 1, Bratislava\nOrganizátor: katRande");

        $this->assertSame(['name' => 'Dom Quo Vadis', 'city' => 'Bratislava', 'street' => 'Veterná 1'], $result);

        $result = $this->extractor->extractVenue('Kde: Dóm sv. Martina (Katedrála sv. Martina), Rudnayovo námestie 1, Bratislava');

        $this->assertSame('Dóm sv. Martina (Katedrála sv. Martina)', $result['name']);
        $this->assertSame('Rudnayovo námestie 1', $result['street']);
        $this->assertSame('Bratislava', $result['city']);
    }

    #[Test]
    public function it_does_not_name_the_venue_after_its_address(): void
    {
        $result = $this->extractor->extractVenue('Kde: Cesta olympionikov 1271/28, Banská Bystrica');

        $this->assertSame(['name' => null, 'city' => 'Banská Bystrica', 'street' => 'Cesta olympionikov 1271/28'], $result);
    }

    #[Test]
    public function it_keeps_city_first_order_when_the_street_is_last(): void
    {
        $result = $this->extractor->extractVenue('Miesto konania: Sabinov, Gréckokatolícky chrám, Hlavná 5');

        $this->assertSame('Sabinov', $result['city']);
        $this->assertSame('Gréckokatolícky chrám, Hlavná 5', $result['name']);
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
            ."na Kalvárii\nv Nitre\nvo Farskom pastoračnom centre\n"
            .'(vchod z veľkého parkoviska).';

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
            ." príležitosti 800. výročia\npodujatie Osem storočí – jeden príbeh.";

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

    #[Test]
    public function it_reads_the_address_in_parentheses_after_the_venue(): void
    {
        $this->assertSame(
            ['name' => 'Mariánska sála', 'city' => 'Trnava', 'street' => 'Františkánska 33A'],
            $this->extractor->extractVenue("Kde:\nMariánska sála (Františkánska 33A, Trnava)\nOrganizátor:\nMariánska sála"),
        );

        // Zátvorka bez čiarky je súčasťou názvu.
        $this->assertSame(
            'Katedrála (Spišská Kapitula)',
            $this->extractor->extractVenue('Kde: Katedrála (Spišská Kapitula)')['name'],
        );
    }
}
