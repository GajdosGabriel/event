<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Stĺpce a indexy tabuliek pre runtime kontroly typu „má táto tabuľka `status`?".
 *
 * `Schema::hasColumn()` je pri každom volaní dotaz do `information_schema`
 * (na MySQL desiatky ms) a statické pole v traite žije len jeden request —
 * verejný výpis tak pri každom zobrazení platil 2–6 takých dotazov. Schéma sa
 * mení len migráciou, preto sa drží v cache a po `migrate` sa zneplatní
 * (AppServiceProvider počúva MigrationsEnded).
 */
final class SchemaCache
{
    private const PREFIX = 'schema-cache:';

    /** @var array<string, array<int, string>> */
    private static array $memo = [];

    private static ?int $version = null;

    public static function hasColumn(string $table, string $column): bool
    {
        return in_array(strtolower($column), self::remember("{$table}:columns",
            fn () => array_map('strtolower', Schema::getColumnListing($table))), true);
    }

    public static function hasIndex(string $table, string $index): bool
    {
        return in_array($index, self::remember("{$table}:indexes",
            fn () => array_column(Schema::getIndexes($table), 'name')), true);
    }

    /**
     * Zneplatní všetko naraz — po migrácii sa schéma mohla zmeniť. Verzia
     * v kľúči odreže aj záznamy, ktoré nacachovali iné procesy.
     */
    public static function flush(): void
    {
        Cache::forever(self::PREFIX.'version', self::version() + 1);
        self::$memo = [];
        self::$version = null;
    }

    /**
     * @param  callable(): array<int, string>  $resolve
     * @return array<int, string>
     */
    private static function remember(string $key, callable $resolve): array
    {
        return self::$memo[$key] ??= Cache::rememberForever(
            self::PREFIX.'v'.self::version().':'.$key,
            $resolve,
        );
    }

    private static function version(): int
    {
        return self::$version ??= (int) Cache::get(self::PREFIX.'version', 0);
    }
}
