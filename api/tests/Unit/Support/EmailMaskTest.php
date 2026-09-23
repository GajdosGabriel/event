<?php

namespace Tests\Unit\Support;

use App\Support\EmailMask;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EmailMaskTest extends TestCase
{
    #[Test]
    #[DataProvider('cases')]
    public function it_masks_the_local_part_and_keeps_the_domain(?string $email, ?string $expected): void
    {
        $this->assertSame($expected, EmailMask::mask($email));
    }

    public static function cases(): array
    {
        return [
            'bežná adresa' => ['gabo@gmail.com', 'g•••o@gmail.com'],
            'dlhé meno' => ['gabriel.gajdos@firma.sk', 'g•••s@firma.sk'],
            'dva znaky' => ['ab@x.sk', 'a•••@x.sk'],
            'jeden znak' => ['a@x.sk', 'a•••@x.sk'],
            'diakritika' => ['šťastný@x.sk', 'š•••ý@x.sk'],
            'medzery okolo' => ['  gabo@gmail.com ', 'g•••o@gmail.com'],
            'bez zavináča' => ['nieco', '•••'],
            'prázdny local' => ['@x.sk', '•••@x.sk'],
            'prázdny' => ['', null],
            'null' => [null, null],
        ];
    }
}
