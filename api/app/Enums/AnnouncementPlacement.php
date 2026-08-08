<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;

/** Kam vo verejnom layoute oznam patrí. */
enum AnnouncementPlacement: string implements HasLabel
{
    use ProvidesOptions;

    /** Pás nad hlavičkou — kampane a plošné upozornenia. */
    case Top = 'top';

    /** Pás nad pätičkou — doplnkové informácie. */
    case Bottom = 'bottom';

    public function label(): string
    {
        return __('announcements.placements.' . $this->value);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
