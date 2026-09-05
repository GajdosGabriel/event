<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    /**
     * Pseudo-obec „Celé Slovensko“ — zberná hodnota pre záznam, ktorého obec
     * nepoznáme. Nie je to obec: v číselníku sedí pod kraj 9, ktorý žiadny
     * kraj nie je (viď App\Support\SlovakRegions).
     *
     * Kľúč je slug, nie id. Číselník zanáša migrácia z `municipalities.sql`
     * s pevnými id, takže dnes je to 4209 — ale natvrdo zapísané číslo v kóde
     * nikomu nepovie, čo znamená, a po prípadnom preseedovaní ukáže na inú
     * obec. Slug prežije oboje.
     */
    public const NATIONWIDE_SLUG = 'cele-slovensko';

    /**
     * Id zberného „Celé Slovensko“.
     *
     * Zámerne bez cache: je to jeden lookup na unikátnom indexe a volá sa
     * zriedka. Cache by tu priniesla len riziko zaseknutej hodnoty medzi
     * testami a po preseedovaní číselníka.
     */
    public static function nationwideId(): ?int
    {
        $id = static::query()->where('slug', self::NATIONWIDE_SLUG)->value('id');

        return $id === null ? null : (int) $id;
    }
}
