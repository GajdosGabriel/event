<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dorovnanie väzieb, ktoré ako jediné ostali len dohodou v kóde.
 *
 * Schéma má cudzie kľúče všade — canal_user, canal_venue, event_tag, tickets,
 * questions, messages… Bez nich ostalo sedem stĺpcov, a nie sú to okrajové:
 * `events.canal_id` a `events.user_id` držia podujatie pri organizátorovi
 * a majiteľovi. Observery `forceDeleted()` sú prázdne stuby, takže tvrdé
 * zmazanie kanála dnes ticho nechá podujatia ukazovať do prázdna — a verejný
 * výpis ich vykreslí bez organizátora. `restrictOnDelete()` z toho spraví
 * hlasnú chybu namiesto tichej diery.
 *
 * Chýbali aj indexy pod týmito stĺpcami. `canals.municipality_id` bolí najviac
 * teraz, keď ho backfill naplnil skutočnými obcami: filter obce nad
 * organizátormi na ňom robil full scan (EXPLAIN: type=ALL, possible_keys=NULL).
 * `venues.village_id` ho ťahá pri filtri obce nad podujatiami cez
 * `whereHas('venue')`.
 *
 * Index sa zakladá výslovne, nie cez automatiku MySQL: pomenovaný podľa
 * konvencie Laravelu prežije prípadné zrušenie kľúča a je vidieť v `SHOW INDEX`
 * pod tým, čo znamená.
 *
 * Overené na produkčných dátach: nula osirených riadkov vo všetkých siedmich
 * väzbách. Keby ich niekde bolo, migrácia padne — a to je správne: znamenalo by
 * to dieru v dátach, ktorú treba vidieť, nie ticho preskočiť.
 */
return new class extends Migration
{
    /**
     * [tabuľka, stĺpec, cieľová tabuľka, akcia pri zmazaní]
     *
     * `restrict` pri stĺpcoch NOT NULL a pri číselníkoch (obec sa nemaže).
     * `null` tam, kde je stĺpec nullable a záznam bez väzby dáva zmysel:
     * kanál prežije zánik firmy, používateľ zánik osobného kanála.
     */
    private const RELATIONS = [
        ['events', 'canal_id', 'canals', 'restrict'],
        ['events', 'user_id', 'users', 'restrict'],
        ['canals', 'municipality_id', 'municipalities', 'restrict'],
        ['canals', 'organization_id', 'organizations', 'null'],
        ['venues', 'village_id', 'municipalities', 'restrict'],
        ['users', 'canal_id', 'canals', 'null'],
        ['organizations', 'village_id', 'municipalities', 'restrict'],
    ];

    public function up(): void
    {
        foreach (self::RELATIONS as [$table, $column, $references, $onDelete]) {
            if (! $this->usable($table, $column, $references)) {
                continue;
            }

            $indexName = "{$table}_{$column}_index";

            if (! $this->hasIndex($table, $indexName) && ! $this->hasIndexOn($table, $column)) {
                Schema::table($table, function (Blueprint $blueprint) use ($column, $indexName) {
                    $blueprint->index($column, $indexName);
                });
            }

            if ($this->hasForeignKey($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $column, $references, $onDelete) {
                $foreign = $blueprint->foreign($column, "{$table}_{$column}_foreign")
                    ->references('id')
                    ->on($references);

                $onDelete === 'null' ? $foreign->nullOnDelete() : $foreign->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::RELATIONS as [$table, $column, $references, $onDelete]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            if ($this->hasForeignKey($table, $column)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                    $blueprint->dropForeign("{$table}_{$column}_foreign");
                });
            }

            // Index sa zámerne nechá: sám o sebe neprekáža a jeho zrušenie by
            // vrátilo full scan na filtri obce.
        }
    }

    private function usable(string $table, string $column, string $references): bool
    {
        return Schema::hasTable($table)
            && Schema::hasTable($references)
            && Schema::hasColumn($table, $column);
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    /** Stĺpec už môže byť prvý v inom indexe — druhý by bol len réžia navyše. */
    private function hasIndexOn(string $table, string $column): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->where('seq_in_index', 1)
            ->exists();
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        return DB::table('information_schema.key_column_usage')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->whereNotNull('referenced_table_name')
            ->exists();
    }
};
