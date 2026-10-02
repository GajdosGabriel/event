<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nové popisy importovaných kanálov typu organizácia (2. 10. 2026).
 *
 * Importované kanály mali buď generickú vetu („X — organizátor podujatí."),
 * alebo krátky popis z AI. Každý z kanálov v database/data/canal_descriptions_2026_10_02.json
 * dostáva popis v troch odsekoch (kto organizácia je, čo organizuje, komu je
 * kanál určený).
 *
 * Zmena sa robí len keď kanál stále sedí na id aj na názov a jeho súčasný popis
 * je presne ten, z ktorého sa vychádzalo (porovnáva sa MD5 pôvodného textu), takže
 * popis, ktorý medzitým upravil vlastník kanála alebo správca, ostane nedotknutý.
 * Pôvodné texty sa nearchivujú — down() preto nič nevracia.
 */
return new class extends Migration
{
    private const DATA_FILE = __DIR__.'/../data/canal_descriptions_2026_10_02.json';

    public function up(): void
    {
        if (! Schema::hasTable('canals') || ! is_file(self::DATA_FILE)) {
            return;
        }

        /** @var array<string, array{name: string, old: string, body: string}> $descriptions */
        $descriptions = json_decode((string) file_get_contents(self::DATA_FILE), true, 512, JSON_THROW_ON_ERROR);

        foreach ($descriptions as $id => $description) {
            $canal = DB::table('canals')
                ->where('id', (int) $id)
                ->where('name', $description['name'])
                ->where('identity_mode', 'organization')
                ->first(['id', 'body']);

            // MD5 sa porovnáva v PHP: v SQL sa kolácia výsledku MD5() líši od
            // kolácie spojenia a porovnanie padá na „Illegal mix of collations".
            if ($canal === null || md5((string) $canal->body) !== $description['old']) {
                continue;
            }

            // Bez updated_at: oprava popisu nemá posúvať „naposledy upravené".
            DB::table('canals')->where('id', $canal->id)->update(['body' => $description['body']]);
        }
    }

    public function down(): void
    {
        // Pôvodné popisy sa neukladajú, späť sa nevracajú.
    }
};
