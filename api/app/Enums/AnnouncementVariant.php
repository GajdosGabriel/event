<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Farebná schéma oznamu.
 *
 * Zámerne kľúč, nie hotové Tailwind triedy: CSS sa generuje zo zdrojákov, takže
 * trieda uložená len v databáze by v builde neexistovala a banner by ostal
 * nenaštýlovaný. Vzhľad každého kľúča je v `ui/src/styles.css` (`.announcement-*`).
 */
enum AnnouncementVariant: string implements HasLabel
{
    use ProvidesOptions;

    case Blue = 'blue';
    case Green = 'green';
    case Orange = 'orange';
    case Red = 'red';
    case Purple = 'purple';
    case Dark = 'dark';

    public function label(): string
    {
        return __('announcements.variants.' . $this->value);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
