<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odtlačok podkladov, s ktorými hľadanie naposledy bežalo (App\Support\LookupInputs).
 * Bez nových podkladov sa profil, poloha ani e-mail nehľadajú znova.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_enrichments', function (Blueprint $table) {
            $table->char('inputs_hash', 40)->nullable()->after('attempts');
        });

        Schema::table('canal_email_searches', function (Blueprint $table) {
            $table->char('inputs_hash', 40)->nullable();
        });

        Schema::create('coordinate_lookups', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->unique(['subject_type', 'subject_id']);
            $table->char('inputs_hash', 40);
            // Zdroj nájdenej polohy (address, venue, municipality); null = nič.
            $table->string('source', 20)->nullable();
            $table->timestamp('looked_up_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coordinate_lookups');

        Schema::table('canal_email_searches', function (Blueprint $table) {
            $table->dropColumn('inputs_hash');
        });

        Schema::table('profile_enrichments', function (Blueprint $table) {
            $table->dropColumn('inputs_hash');
        });
    }
};
