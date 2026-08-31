<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stav AI štítkovania podujatia.
     *
     * Zámerne skutočné stĺpce, nie kľúče v `meta`: výberový dotaz plánovaného
     * príkazu ich potrebuje indexovať a MySQL na netypovanom JSON stĺpci index
     * použiť nevie.
     *
     * `ai_tags_hash` je MD5 zdrojového textu — nezmenené podujatie sa druhýkrát
     * neštítkuje. `ai_tags_attempts` zastaví opakované pokusy po treťom zlyhaní;
     * bez neho by podujatie s trvalo padajúcim volaním viselo vo výbere navždy
     * a každý beh by stálo peniaze.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'ai_tagged_at')) {
                $table->timestamp('ai_tagged_at')->nullable();
            }

            if (! Schema::hasColumn('events', 'ai_tags_hash')) {
                $table->char('ai_tags_hash', 32)->nullable();
            }

            if (! Schema::hasColumn('events', 'ai_tags_attempts')) {
                $table->unsignedTinyInteger('ai_tags_attempts')->default(0);
            }
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index('ai_tagged_at');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['ai_tagged_at']);
            $table->dropColumn(['ai_tagged_at', 'ai_tags_hash', 'ai_tags_attempts']);
        });
    }
};
