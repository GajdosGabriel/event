<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Facety číselníka štítkov — nezávislé osi, na ktorých sa podujatie opisuje.
 *
 * Podujatie má typicky štítok z každého facetu naraz (festival + folklór +
 * pre rodiny + vonku). Plochý zoznam by nútil vyberať medzi „festival"
 * a „folklór", hoci to nie sú alternatívy; pri odporúčaniach sa navyše každý
 * facet váži zvlášť — „chodí na folklór" je iný signál než „chodí na festivaly".
 */
enum TagGroup: string implements HasLabel
{
    use ProvidesOptions;

    /** Aký druh podujatia to je — koncert, divadlo, turnaj… */
    case Format = 'format';

    /** O čom to je — folklór, rock, história, gastro… */
    case Topic = 'topic';

    /** Pre koho — pre deti, pre seniorov… */
    case Audience = 'audience';

    /** Praktický charakter — vonku, vstup voľný, viacdňové… */
    case Attribute = 'attribute';

    public function label(): string
    {
        return __('tags.groups.' . $this->value);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
