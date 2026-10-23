<?php

use App\Support\ImportedText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Odstráni z popisov podujatí redakčnú hlavičku TK KBS
 * („Bratislava 2. októbra (TK KBS)"). Bola takmer v každom podujatí
 * z tkkbs.sk — raz ako samostatný odsek, inokedy na začiatku prvého.
 * Nové importy ju vynechávajú v HtmlBodyCleaner.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        DB::table('events')
            ->where('body', 'like', '%TK%KBS%')
            ->select(['id', 'body'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $body = (string) $row->body;
                    $clean = ImportedText::stripDatelines(ImportedText::stripNewsroomCodes($body));

                    if ($clean === $body || trim(strip_tags($clean)) === '') {
                        continue;
                    }

                    DB::table('events')->where('id', $row->id)->update([
                        'body' => $clean,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * Zámerne prázdne — hlavička čitateľovi nič nehovorila.
     */
    public function down(): void {}
};
