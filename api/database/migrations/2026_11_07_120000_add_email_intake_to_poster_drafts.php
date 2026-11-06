<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Podujatie poslané e-mailom na kontaktnú adresu (PosterEmailIntake).
 *
 * Koncept vzniká rovnako ako z plagátu, len ho drží konverzácia v Accounte:
 * odpoveď odosielateľa (doplnenie chýbajúcich údajov) príde ako
 * `message.received` k tomuto konceptu. Odkaz na koncept chodí v každom
 * e-maile, preto je token uložený aj šifrovane — hash v `token` ostáva
 * jediný, podľa ktorého sa koncept otvára.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poster_drafts', function (Blueprint $table) {
            if (! Schema::hasColumn('poster_drafts', 'channel')) {
                $table->string('channel', 10)->default('web')->after('source_kind');
                $table->uuid('account_conversation_id')->nullable()->unique()->after('channel');
                $table->string('last_message_id', 64)->nullable()->after('account_conversation_id');
                $table->unsignedTinyInteger('email_rounds')->default(0)->after('last_message_id');
                $table->text('email_token')->nullable()->after('email_rounds');
            }
        });
    }

    public function down(): void
    {
        Schema::table('poster_drafts', function (Blueprint $table) {
            if (Schema::hasColumn('poster_drafts', 'channel')) {
                $table->dropUnique(['account_conversation_id']);
                $table->dropColumn(['channel', 'account_conversation_id', 'last_message_id', 'email_rounds', 'email_token']);
            }
        });
    }
};
