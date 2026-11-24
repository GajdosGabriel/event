<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    /**
     * Mestské časti sú v číselníku samostatné obce („Bratislava - Staré Mesto")
     * a miesta bývajú zapísané raz pod mestom, raz pod časťou. Pri hľadaní
     * miest preto berieme celé mesto: pri meste aj jeho časti, pri časti aj
     * mesto a ostatné časti.
     *
     * @return array<int, int>
     */
    public static function cityWithDistrictIds(int $id): array
    {
        $name = (string) static::query()->whereKey($id)->value('fullname');
        $city = trim(Str::before($name, ' - '));

        if ($city === '') {
            return [$id];
        }

        return static::query()
            ->where(static fn ($q) => $q->where('fullname', $city)->orWhere('fullname', 'like', self::escapeLike($city).' - %'))
            ->pluck('id')
            ->map(static fn ($value) => (int) $value)
            ->push($id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Obce, ktorých názov začína hľadaným výrazom („Brati", „Staré Mesto"),
     * rozšírené o mesto a jeho časti. Kratší výraz než 3 znaky nič nevráti —
     * „Ba" by trafilo stovky obcí.
     *
     * @return array<int, int>
     */
    public static function idsMatchingName(string $term, int $limit = 20): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 3) {
            return [];
        }

        $like = self::escapeLike($term);

        return static::query()
            ->where('slug', '!=', self::NATIONWIDE_SLUG)
            ->where(static fn ($q) => $q->where('fullname', 'like', $like.'%')->orWhere('fullname', 'like', '% - '.$like.'%'))
            ->limit($limit)
            ->pluck('id')
            ->flatMap(static fn ($id) => static::cityWithDistrictIds((int) $id))
            ->unique()
            ->values()
            ->all();
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
