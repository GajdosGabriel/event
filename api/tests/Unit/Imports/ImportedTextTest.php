<?php

namespace Tests\Unit\Imports;

use App\Support\ImportedText;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportedTextTest extends TestCase
{
    #[Test]
    public function it_strips_newsroom_codes(): void
    {
        $this->assertSame('Kontakt: Jozef | dalej', ImportedText::stripNewsroomCodes('Kontakt: Jozef (TK KBS, is, eg, ml; pz) 20260616014 | dalej'));
        $this->assertSame('Farnosti', ImportedText::stripNewsroomCodes('Farnosti (TK KBS, fd, ml; pz)'));
        $this->assertSame('Bratislava 25. júna (TK KBS) Text', ImportedText::stripNewsroomCodes('Bratislava 25. júna (TK KBS) Text'));
    }

    #[Test]
    public function it_strips_tkkbs_datelines(): void
    {
        $this->assertSame('Text', ImportedText::stripDatelines('Bratislava 25. júna (TK KBS) Text'));
        $this->assertSame('<p>Úvod</p>', ImportedText::stripDatelines("<p>Bratislava 22. októbra (TK KBS)</p>\n<p>Úvod</p>"));
        $this->assertSame('<p><strong>Košice</strong></p><p>Farnosť pozýva</p>', ImportedText::stripDatelines('<p><strong>Košice</strong></p><p><strong>Košice 22. októbra (TK KBS)</strong> – Farnosť pozýva</p>'));
        $this->assertSame('<p>Registrácia sa začala.</p>', ImportedText::stripDatelines('<p>Vatikán/Južná Kórea 22. októbra (TK KBS) Registrácia sa začala.</p>'));
        $this->assertSame('<p>Zbierka</p>', ImportedText::stripDatelines('<p>Žilina, 2. marca (TK KBS)</p><p>Zbierka</p>'));
        $this->assertSame('<p>Úvod</p>', ImportedText::stripDatelines('<p>februára (TK KBS)</p><p>Úvod</p>'));
        $this->assertSame('<p>TK KBS informoval Marián Oravec</p>', ImportedText::stripDatelines('<p>TK KBS informoval Marián Oravec, Bratislava 2. októbra (TK KBS)</p>'));
        $this->assertSame('<ul><li>Cena: 5 €</li></ul>', ImportedText::stripDatelines('<ul><li>Cena: 5 €</li><li><strong>Kontakt:</strong> Bratislava 2. októbra (TK KBS)</li></ul>'));
        $this->assertSame('<p>Kontakt: Biskupstvo Nitra</p>', ImportedText::stripDatelines('<p>Kontakt: Biskupstvo Nitra (TK KBS)</p>'));
        $this->assertSame('<p>Omša 2. októbra o 18:00</p>', ImportedText::stripDatelines('<p>Omša 2. októbra o 18:00</p>'));
    }

    #[Test]
    public function it_strips_the_tkkbs_portal_footer_with_its_credit(): void
    {
        $footer = 'Upozorniť na chybu v správe | [naspäť] O nás | Ochrana osobných údajov | Logo | Copyright | Fotobanka | Hovorca KBS | Zoznam hovorcov diecéz TV Lux naživo | Rádio Lumen | Rádio Vatikán | SSV | Katolícke noviny | Výveska | Do kostola Nastavenie Cookies (c) TK KBS 2003 - 2026';

        $this->assertSame('<p>Úvod</p><p>Zdroje: Bratislavská arcidiecéza/Cliptime, redakčne upravené</p>', ImportedText::stripPortalFooter('<p>Úvod</p><p>Zdroje: Bratislavská arcidiecéza/Cliptime, redakčne upravené | '.$footer.'</p>'));
        $this->assertSame('<p>TK KBS informovala Katarína Kurucová</p>', ImportedText::stripPortalFooter('<p>TK KBS informovala Katarína Kurucová | '.$footer.'</p>'));
        $this->assertSame('<p>Zdroj: Prešovská archieparchia, redakčne upravené</p>', ImportedText::stripPortalFooter('<p>Zdroj: Prešovská archieparchia, redakčne upravené. 20261103003 | '.$footer.'</p>'));
        $this->assertSame('<p>Úvod</p><p></p>', ImportedText::stripPortalFooter('<p>Úvod</p><p> | '.$footer.'</p>'), 'Prázdny odsek zahodí až HtmlBodyCleaner.');
        $this->assertSame('<ul><li></li></ul>', ImportedText::stripPortalFooter('<ul><li>Kontakt: [naspäť] O nás | Ochrana osobných údajov | Copyright | Nastavenie Cookies (c) TK KBS 2003 - 2026</li></ul>'));
        $this->assertSame("Program\n\nViac na www.sbb.sk. TK KBS informoval Tibor Ujlacký", ImportedText::stripPortalFooter("Program\n\nViac na www.sbb.sk. TK KBS informoval Tibor Ujlacký | ".$footer));
        $this->assertSame('<p>Zdroje: TASR</p>', ImportedText::stripPortalFooter('<p>Zdroje: TASR</p>'), 'Bez päty nemení nič.');
    }

    #[Test]
    public function it_adds_the_missing_space_after_sv(): void
    {
        $this->assertSame('Sv. omša a sv. Ján', ImportedText::fixAbbreviationSpacing('Sv.omša a sv.Ján'));
        $this->assertSame('Sv. omša', ImportedText::fixAbbreviationSpacing('Sv. omša'));
        $this->assertSame('https://www.ssv.sk/x www.sv.sk', ImportedText::fixAbbreviationSpacing('https://www.ssv.sk/x www.sv.sk'));
    }

    #[Test]
    public function it_normalizes_venue_names(): void
    {
        $this->assertSame('Dolný Kubín', ImportedText::venueName('Dolný Kubín (okres)'));
        $this->assertSame('Kostol sv. Štefana Uhorského', ImportedText::venueName('Kostole sv. Štefana Uhorského'));
        $this->assertSame('Seminárny kostol na Hlavnej 89', ImportedText::venueName('Seminárnom kostole na Hlavnej 89'));
        $this->assertSame('Kláštor minoritov', ImportedText::venueName('Kláštore minoritov'));
        $this->assertSame('Kostol sv. Jána', ImportedText::venueName('Kostol sv. Jána'));
    }
}
