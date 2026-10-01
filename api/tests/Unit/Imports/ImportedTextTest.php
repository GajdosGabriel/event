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
