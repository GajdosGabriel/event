<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FULLTEXT indexy pre vyhľadávanie v podujatiach, kanáloch a miestach.
 *
 * `HasCommonFilters::scopeBySearch()` hľadalo cez `LIKE '%term%'`, čo nevie
 * použiť index a zároveň porovnáva celý reťazec ako súvislý podreťazec —
 * „koncert Košice" preto nenašlo nič, aj keď obe slová v podujatí boli.
 *
 * Kombinácie stĺpcov musia presne sedieť na to, čo trait posiela do MATCH():
 * MySQL aj MariaDB vyžadujú index nad presne tou istou množinou stĺpcov.
 *   - `{table}_search_primary_fulltext` = primárne stĺpce (name/title, email)
 *   - `{table}_search_fulltext`         = primárne + sekundárne (body)
 * Prvý slúži len na zoradenie podľa relevancie (zhoda v názve pred zhodou
 * v popise), druhý je ten, ktorý filtruje.
 *
 * Keď indexy chýbajú (napr. iný driver), trait to zistí a ticho spadne
 * späť na pôvodné LIKE — migrácia je preto bezpečná aj čiastočne.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{primary: array<int, string>, secondary: array<int, string>}>
     */
    private array $tables = [
        'events' => ['primary' => ['name', 'email'], 'secondary' => ['body']],
        'canals' => ['primary' => ['name', 'email'], 'secondary' => ['body']],
        'venues' => ['primary' => ['name', 'email'], 'secondary' => ['body']],
    ];

    public function up(): void
    {
        if (! $this->supportsFulltext()) {
            return;
        }

        foreach ($this->tables as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $primary = $this->presentColumns($table, $columns['primary']);
            $all = $this->presentColumns($table, array_merge($columns['primary'], $columns['secondary']));

            if ($primary === []) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $primary, $all) {
                if (! $this->hasIndex($table, $table . '_search_primary_fulltext')) {
                    $blueprint->fullText($primary, $table . '_search_primary_fulltext');
                }

                if ($all !== $primary && ! $this->hasIndex($table, $table . '_search_fulltext')) {
                    $blueprint->fullText($all, $table . '_search_fulltext');
                }
            });
        }
    }

    public function down(): void
    {
        if (! $this->supportsFulltext()) {
            return;
        }

        foreach (array_keys($this->tables) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                foreach ([$table . '_search_fulltext', $table . '_search_primary_fulltext'] as $index) {
                    if ($this->hasIndex($table, $index)) {
                        $blueprint->dropFullText($index);
                    }
                }
            });
        }
    }

    private function supportsFulltext(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function presentColumns(string $table, array $columns): array
    {
        return array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn($table, $column)
        ));
    }

    private function hasIndex(string $table, string $index): bool
    {
        foreach (Schema::getIndexes($table) as $existing) {
            if (($existing['name'] ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
