<?php

namespace App\Enums;

/**
 * Na kom je vlákno podpory.
 *
 * `open` = čaká na podporu, `answered` = podpora odpovedala a čaká sa na
 * používateľa. Bez toho rozlíšenia by admin nevedel, čo ešte nikto nevybavil.
 */
enum SupportStatus: string
{
    case Open = 'open';
    case Answered = 'answered';
    case Closed = 'closed';

    public function label(): string
    {
        return __('support.status.'.$this->value);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
