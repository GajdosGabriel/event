<?php

namespace Tests\Unit\Imports;

use App\Services\Imports\OrganizerName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OrganizerNameTest extends TestCase
{
    #[Test]
    public function it_keeps_only_the_first_of_a_comma_separated_list(): void
    {
        $this->assertSame(
            'Múzeum obetí komunizmu v Košiciach',
            OrganizerName::sanitize('Múzeum obetí komunizmu v Košiciach, Ústav pamäti národa (ÚPN), OZ samizdat.sk a Dom Quo Vadis'),
        );
    }

    #[Test]
    public function it_cuts_partners_after_v_spolupraci_s(): void
    {
        $this->assertSame(
            'Fórum života, o.z',
            OrganizerName::sanitize('Fórum života, o.z. v spolupráci s Áno pre život, n.o'),
        );
    }

    #[Test]
    public function it_keeps_legal_forms_that_belong_to_the_name(): void
    {
        $this->assertSame('CYRILOMETODIADA, o. z', OrganizerName::sanitize('CYRILOMETODIADA, o. z., Nadácia PRO PATRIA'));
        $this->assertSame('Cor Sancti Martini, n. o', OrganizerName::sanitize('Cor Sancti Martini, n. o.'));
    }

    #[Test]
    public function it_does_not_split_a_single_name_containing_a(): void
    {
        $this->assertSame(
            'Rada pre mládež a univerzity KBS',
            OrganizerName::sanitize('Rada pre mládež a univerzity KBS'),
        );
    }
}
