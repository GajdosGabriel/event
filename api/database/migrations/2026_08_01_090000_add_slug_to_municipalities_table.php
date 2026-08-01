<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Obce sú číselník — landing stránka `/podujatia/mesto/{slug}` potrebuje
 * stabilný, čitateľný kľúč v URL. Číselné id by z nej urobilo neindexovateľnú
 * adresu, o akej je celá fáza „dosah".
 *
 * Slug zanáša migrácia, nie seeder: na produkcii sa `db:seed` nepúšťa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('municipalities')) {
            return;
        }

        if (! Schema::hasColumn('municipalities', 'slug')) {
            Schema::table('municipalities', function (Blueprint $table) {
                $table->string('slug')->nullable()->after('shortname');
            });
        }

        $this->backfillSlugs();

        // Unikátny index až po naplnení — na prázdnom stĺpci by ho druhý NULL
        // ešte pustil, ale po backfille chceme tvrdú záruku, že jeden slug
        // ukazuje na jednu obec.
        if (! $this->hasIndex('municipalities_slug_unique')) {
            Schema::table('municipalities', function (Blueprint $table) {
                $table->unique('slug', 'municipalities_slug_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('municipalities', 'slug')) {
            return;
        }

        Schema::table('municipalities', function (Blueprint $table) {
            if ($this->hasIndex('municipalities_slug_unique')) {
                $table->dropUnique('municipalities_slug_unique');
            }

            $table->dropColumn('slug');
        });
    }

    /**
     * 216 obcí zdieľa `shortname` (Abrahámovce, Nová Ves, …). Kolízia sa rieši
     * príponou `-{id}`: je deterministická a poradie podľa id znamená, že
     * opakovaný beh migrácie priradí tie isté slugy.
     */
    private function backfillSlugs(): void
    {
        $taken = DB::table('municipalities')
            ->whereNotNull('slug')
            ->pluck('slug')
            ->flip()
            ->toArray();

        // chunkById, nie chunk: dopyt filtruje na `slug IS NULL` a zároveň slug
        // zapisuje, takže stránkovanie cez OFFSET by po každom chunku preskočilo
        // rovnako veľký kus tabuľky (prvý beh nechal 2000 obcí bez slugu).
        DB::table('municipalities')
            ->select('id', 'shortname', 'fullname')
            ->whereNull('slug')
            ->chunkById(500, function ($rows) use (&$taken) {
                foreach ($rows as $row) {
                    $base = Str::slug($row->shortname ?: $row->fullname);
                    $base = $base !== '' ? $base : 'obec';
                    $slug = isset($taken[$base]) ? "{$base}-{$row->id}" : $base;

                    $taken[$slug] = true;

                    DB::table('municipalities')->where('id', $row->id)->update(['slug' => $slug]);
                }
            });
    }

    private function hasIndex(string $index): bool
    {
        return collect(Schema::getIndexes('municipalities'))
            ->contains(fn (array $definition) => $definition['name'] === $index);
    }
};
