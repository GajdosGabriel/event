<?php

use App\Services\Imports\HtmlBodyCleaner;
use App\Support\ImportedText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Odstráni z popisov podujatí pätu webu tkkbs.sk („| Upozorniť na chybu
 * v správe | O nás | … Nastavenie Cookies (c) TK KBS 2003 - 2026") a nadpis,
 * ktorý AI nad ňu pridala („Dôležité odkazy"), ak pod ním nič neostalo.
 * Redakčný kredit pred pätou („Zdroje: TASR", „TK KBS informovala …")
 * ostáva. Nové importy pätu vynechávajú v HtmlBodyCleaner.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        $cleaner = app(HtmlBodyCleaner::class);

        DB::table('events')
            ->where('body', 'like', '%TK%KBS%')
            ->select(['id', 'body'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($cleaner): void {
                foreach ($rows as $row) {
                    $body = (string) $row->body;

                    if (ImportedText::stripPortalFooter($body) === $body) {
                        continue;
                    }

                    $clean = $cleaner->cleanHtmlString($body);

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
     * Zámerne prázdne — päta čitateľovi nič nehovorila.
     */
    public function down(): void {}
};
